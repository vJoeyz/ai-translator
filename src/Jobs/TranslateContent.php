<?php

namespace AiTranslator\Jobs;

use Illuminate\Support\Facades\Log;
use Statamic\Facades\Search;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Facades\Entry;
use Statamic\Facades\Collection;
use Illuminate\Http\Request;
use Statamic\Fieldtypes\Bard\Augmentor;

use Illuminate\Support\Facades\Config;
use Statamic\Facades\Fieldset;


use Statamic\Facades\Blueprint;

use Statamic\Facades\Content;
use App\Helpers\Utils;
use Illuminate\Support\Facades\Http;


class TranslateContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DEEPL_LOCALES = [
        'AR', 'BG', 'CS', 'DA', 'DE', 'EL', 'EN-GB', 'EN-US', 'ES', 'ET', 'FI',
        'FR', 'HU', 'ID', 'IT', 'JA', 'KO', 'LT', 'LV', 'NB', 'NL', 'PL',
        'PT-BR', 'PT-PT', 'RO', 'RU', 'SK', 'SL', 'SV', 'TR', 'UK', 'ZH',
        'ZH-HANS', 'ZH-HANT'
    ];

    private $apiUrl;
    private $sourceLang;
    private $formality;
    private $glossaryId;
    private $shouldTranslateSlugs;
    private $preserveFormatting;

    private $apiKeyPrivate = null;
    private $service;
    public $language = null;

    private $content;
    private $contentType;
    private $defaultData;
    private $localizedData;
    private $targetLocale;
    private $translatableFields;
    private $translatableData;
    private $fieldKeys;
    private $dataToTranslate;

    private $translatedData;
    private $supportedFieldtypes = [
        'array', 'bard', 'grid', 'list', 'markdown', 'redactor', 'replicator',
        'table', 'tags', 'text', 'textarea',
    ];
    private $textFieldtypes = [
        'text', 'textarea', 'markdown', 'redactor',
    ];

    private $excludedFields = [
        'terms',
    ];
    private $excludedFieldtypes = [
        'taxonomy',
    ];
    private $translatedContent;
    private $row;
    private $siteData;

    private $pathToJson = [];


    public function __construct($row, $siteData, $apiKeyPrivate, $locale)
    {
        $this->row = $row;
        $this->siteData = $siteData;
        $this->apiKeyPrivate = $apiKeyPrivate;
        $this->language = $this->toAllowedLocale($locale);

        $this->apiUrl = config('ai-translator.ai-translator.ai_translator_api_url');
        $this->sourceLang = config('ai-translator.ai-translator.ai_translator_source_lang');
        $this->formality = config('ai-translator.ai-translator.ai_translator_formality');
        $this->glossaryId = config('ai-translator.ai-translator.ai_translator_glossary_id');
        $this->shouldTranslateSlugs = config('ai-translator.ai-translator.ai_translator_translate_slugs');
        $this->preserveFormatting = config('ai-translator.ai-translator.ai_translator_preserve_formatting');
    }

    public function handle()
    {
        $page = Entry::query()
            ->where('id', $this->row)
            ->first();

        if ($page->locale == $this->siteData->handle) {
            return;
        }


        $pageLocalizations = $page->descendants();
        $origin = null;
        if ($page->origin()) {
            $origin = Entry::query()->where('id', $page->origin()->id)->first();
            if ($origin) {
                if ($origin->locale == $this->siteData->handle) {
                    $pageLocalizations = [$origin];


                }
            }

        }


        $translatedPageExists = false;
        $newPage = null;

        foreach ($pageLocalizations as $pageTranslation) {
            if ($pageTranslation->locale == $this->siteData->handle) {
                $translatedPageExists = true;
                $newPage = $pageTranslation;

                $newPage->data($page->data());

                $this->translatedContent = $newPage;
                break;

            }
        }

        if ($translatedPageExists == false) {

            $slug = $page->slug();

            if ($this->shouldTranslateSlugs) {
                $response = $this->translateWithDeepl($slug, $this->apiKeyPrivate, 'EN');
                if (isset($response['translations'][0]['text'])) {
                    $slug = $response['translations'][0]['text'];

                }
            }

            $newPage = Entry::make()
                ->slug($slug)
                ->origin($page->id)
                ->locale($this->siteData->handle)
                ->collection(Collection::findByHandle($page->collection->handle))
                ->data($page->data())
                ->blueprint($page->blueprint->handle);


            $newPage->save();

            Log::debug('New Page Created', [
                'id' => $newPage->id,
                'locale' => $this->siteData->handle,
                'collection' => $newPage->collection()->handle(),
                'slug' => $newPage->slug(),
            ]);

            $this->translatedContent = Entry::find($newPage->id);
        }


        $this->targetLocale = $this->siteData->locale;

        $this->content = $this->translatedContent;

        $this->contentType = $this->content->collection()->handle();


        $this->defaultData = $this->content->data();


        $this->localizedData = $this->defaultData;


        $this->processData();

        $this->dumpTranslatedPaths($this->dataToTranslate);
        $this->translateExportJson();


    }

    private function processData(): void
    {
        $this->getTranslatableFields();
        $this->getTranslatableData();
        $this->getFieldKeys();
        $this->getDataToTranslate();
    }

    private function dumpTranslatedPaths(array $data = [], $path = ''): void
    {
        foreach ($data as $key => $value) {
            $currentPath = $path ? $path . '.' . $key : $key;

            if ((isset($value['type']) && isset($value['content']))) {


                $bardContent = $value;

                $html = (new Augmentor($this))->renderProsemirrorToHtml([
                    'type' => $bardContent['type'],
                    'content' => $bardContent['content'],
                ]);


                $this->pathToJson[$currentPath . '.bard'] = $html;

            } else if (is_array($value)) {
                $this->dumpTranslatedPaths($value, $currentPath);

            } else {
                if ($this->isTranslatableKeyValuePair($value, $key)) {
                    $this->pathToJson[$currentPath . '.text'] = $value;
                }
            }

        }

        $this->pathToJson['slug'] = $this->content->slug;

    }


    private function translateExportJson()
    {


        $translatedItems = $this->pathToJson;

        foreach ($translatedItems as $key => $value) {
            $path = $key;

            $translatedValue = $this->translateWithDeepL($value, 'text');
            $this->setTranslatedValueByPath($this->dataToTranslate, $path, $translatedValue);

        }

        foreach ($this->dataToTranslate as $key => $value) {

            $this->content->set($key, $value);

        }

        $slug = $translatedItems['slug'];


        $this->content->slug($slug);
        $this->content->save();


    }

    private function setTranslatedValueByPath(&$entry, string $path, $value): void
    {
        $refs = &$entry;


        $keys = $this->pathStringToArrayKeys($path);
        $type = array_pop($keys);
        $lastKey = array_pop($keys);

        if ($type === 'text') {
            $toSetValue = $value;
        } elseif ($type === 'bard') {
            $toSetValue = (new Augmentor($this))->renderHtmlToProsemirror($value)['content'];
        } else {
            $toSetValue = $value;
        }

        foreach ($keys as $key) {
            if (is_numeric($key)) {
                $key = (int)$key;
            }

            if (!isset($refs[$key])) {
                $refs[$key] = [];
            }

            $refs = &$refs[$key];
        }

        if ($type === 'text') {
            $refs[$lastKey] = $toSetValue;
        } elseif ($type === 'bard') {
            if (isset($toSetValue[0]['content']) && count($toSetValue) > 1) {
                $refs[$lastKey]['content'] = $toSetValue;
            } elseif (isset($toSetValue[0]['content'])) {
                $refs[$lastKey]['content'] = $toSetValue[0]['content'];
            } else {
                $refs[$lastKey]['content'] = $toSetValue;
            }
        }
    }


    function pathStringToArrayKeys($path)
    {
        $parts = explode('.', $path);


        $result = [];
        foreach ($parts as $part) {
            if (is_numeric($part)) {
                $result[] = (int)$part;
            } else {
                $result[] = $part;
            }
        }
        return $result;
    }


    private function getLocalizableFields(): array
    {
        // Get the fields from the blueprint.
        $fields = collect($this->content->blueprint()->fields()->all());

        // Get the fields where "localizable: true".
        $localizableFields = $fields->filter(function ($field) {
            return isset($field->config()['localizable']) && $field->config()['localizable'] === true;
        });

        // Add the title field, so it can be translated.
        if ($this->contentType !== 'globals' && !$localizableFields->has('title')) {
            $localizableFields->put('title', [
                'type' => 'text',
                'localizable' => true,
            ]);
        }

        return $localizableFields->toArray();
    }


    private function getTranslatableData(): void
    {
        // Ensure $this->defaultData is an array
        $this->defaultData = $this->defaultData->toArray();

        // Ensure $this->translatableFields is an array 
        // $this->translatableFields = $this->translatableFields->toArray();

        $this->translatableData = array_intersect_key($this->defaultData, $this->translatableFields);
    }

    private function getTranslatableFields()
    {
        $localizableFields = $this->getLocalizableFields();


        $this->translatableFields = $localizableFields;
    }

    private function getFieldKeys(): void
    {
        $this->fieldKeys = [
            'allKeys' => $this->getTranslatableFieldKeys($this->translatableFields),
            'setKeys' => $this->getTranslatableSetKeys($this->translatableFields),
        ];
        if (!isset($this->fieldKeys['allKeys']['text'])) {
            $this->fieldKeys['allKeys']['text'] = [];
        }


    }


    private function getDataToTranslate(): void
    {
        // Ensure $this->translatableData and $this->localizedData are arrays
        $translatableData = is_array($this->translatableData) ? $this->translatableData : $this->translatableData->toArray();
        $localizedData = is_array($this->localizedData) ? $this->localizedData : $this->localizedData->toArray();

        $mergedData = array_replace_recursive($translatableData, $localizedData);

        $this->dataToTranslate = $this->unsetSpecialFields($mergedData);

    }

    private function unsetSpecialFields(array $array): array
    {

        if ($this->contentType === 'entry') {
            unset($array['slug']);
        }


        if ($this->contentType === 'page') {
            unset($array['slug']);
        }


        unset($array['id']);

        return $array;
    }

    // private function filterSupportedFieldtypes(array $fields): array
    // {

    //     return collect($fields)
    //         ->map(function ($item) {
    //             if (!isset($item['type'])) {
    //                 return null;
    //             }

    //             switch ($item['type']) {
    //                 case 'replicator':
    //                 case 'bard':
    //                     $item['sets'] = collect($item['sets'] ?? [])
    //                         ->map(function ($set) {
    //                             if (isset($set['fields'])) {
    //                                 $set['fields'] = $this->filterSupportedFieldtypes($set['fields']);
    //                             }
    //                             return $set;
    //                         })
    //                         ->filter(function ($set) {
    //                             return isset($set['fields']) && count($set['fields']) > 0;
    //                         })
    //                         ->toArray();
    //                     break;
    //                 case 'grid':
    //                     if (isset($item['fields'])) {
    //                         $item['fields'] = $this->filterSupportedFieldtypes($item['fields']);
    //                     }
    //                     break;
    //             }

    //             return $item;
    //         })
    //         ->filter(function ($item) {
    //             if (!isset($item['type'])) {
    //                 return false;
    //             }

    //             $supported = in_array($item['type'], $this->supportedFieldtypes);

    //             if (!$supported) {
    //                 return false;
    //             }

    //             switch ($item['type']) {
    //                 case 'replicator':
    //                     return isset($item['sets']) && count($item['sets']) > 0;
    //                 case 'grid':
    //                     return isset($item['fields']) && count($item['fields']) > 0;
    //                 default:
    //                     return true;
    //             }
    //         })
    //         ->filter()
    //         ->toArray();
    // }

    private function getTranslatableFieldKeys(array $fields): array
    {
        $result = [];

        foreach ($fields as $key => $field) {
            if (isset($field['type'])) {

                if (in_array($field['type'], $this->textFieldtypes)) {
                    $result[$key] = $key;
                }

                if (isset($field['sets']) || (isset($field['field']) && isset($field['field']['sets']))) {
                    $sets = $field['sets'] ?? $field['field']['sets'] ?? [];

                    foreach ($sets as $setKey => $set) {
                        if (isset($set['sets'])) {
                            foreach ($set['sets'] as $nestedSetKey => $nestedSet) {
                                if (isset($nestedSet['fields'])) {
                                    $result[$key][$setKey][$nestedSetKey] = $this->getTranslatableFieldKeys($nestedSet['fields']);
                                }
                            }
                        }

                        if (isset($set['fields'])) {
                            $result[$key][$setKey] = $this->getTranslatableFieldKeys($set['fields']);
                        } elseif (is_array($set)) {
                            $result[$key][$setKey] = $this->getTranslatableFieldKeys($set);
                        }
                    }
                }


                if (isset($field['fields'])) {
                    $result[$key] = array_merge($result[$key] ?? [], $this->getTranslatableFieldKeys($field['fields']));
                }
            } else if (is_array($field) && isset($field['import'])) {
                $importedFieldsetName = $field['import'];
                $importedFieldset = Fieldset::find($importedFieldsetName);

                if ($importedFieldset) {
                    $importedFieldsetFields = $importedFieldset->fields()->all()->toArray();
                    $importedKeys = $this->getTranslatableFieldKeys($importedFieldsetFields);

                    if (isset($field['prefix'])) {
                        $prefix = $field['prefix'];
                        $prefixedKeys = [];

                        foreach ($importedKeys as $k => $v) {
                            if (is_array($v)) {
                                $nestedPrefixed = [];
                                foreach ($v as $nk => $nv) {
                                    $nestedPrefixed["{$prefix}{$nk}"] = is_string($nv) ? "{$prefix}{$nv}" : $nv;
                                }
                                $prefixedKeys["{$prefix}{$k}"] = $nestedPrefixed;
                            } else {
                                $prefixedKeys["{$prefix}{$k}"] = is_string($v) ? "{$prefix}{$v}" : $v;
                            }
                        }

                        $result = array_merge($result, $prefixedKeys);
                    } else {
                        $result = array_merge($result, $importedKeys);
                    }

                } else {
                    $result[$importedFieldsetName] = null;
                }

            } elseif (is_array($field)) {
                foreach ($field as $nestedKey => $nestedField) {
                    if (isset($nestedField['type']) && in_array($nestedField['type'], $this->textFieldtypes)) {
                        if (isset($field['handle']) && isset($field['field']['localizable']) && $field['field']['localizable'] === true) {

                            if (!is_numeric($field['handle'])) {
                                $result[$field['handle']] = [];
                            }
                        }
                    } else if (isset($field['handle']) && !is_array($field['field'])) {

                        [$fieldsetName, $fieldName] = explode('.', $field['field']);

                        $fieldset = Fieldset::find($fieldsetName);

                        if ($fieldset) {
                            $fieldsetFields = $fieldset->fields()->all()->toArray();


                            if (isset($fieldsetFields[$fieldName]['fields'])) {
                                $result = array_merge($result, $this->getTranslatableFieldKeys($fieldsetFields[$fieldName]['fields']));
                            } elseif (is_array($fieldsetFields[$fieldName])) {
                                $result = array_merge($result, $this->getTranslatableFieldKeys($fieldsetFields[$fieldName]));
                            }
                        } else {

                            $result[$field['handle']] = [];
                        }
                    } else if (isset($field['import'])) {
                        $importedFieldsetName = $field['import'];
                        $importedFieldset = Fieldset::find($importedFieldsetName);


                        if ($importedFieldset) {
                            $importedFieldsetFields = $importedFieldset->fields()->all()->toArray();


                            $result = array_merge($result, $this->getTranslatableFieldKeys($importedFieldsetFields));
                        } else {

                            $result[$importedFieldsetName] = null;
                        }
                    } elseif (isset($nestedField['fields'])) {

                        $result = array_merge($result, $this->getTranslatableFieldKeys($nestedField['fields']));
                    } elseif (is_array($nestedField)) {

                        $result = array_merge($result, $this->getTranslatableFieldKeys($nestedField));
                    }
                }
            }
        }

        return $result;
    }


    private function getTranslatableSetKeys(array $fields): array
    {
        $result = [];

        foreach ($fields as $key => $field) {
            if (isset($field['type'])) {

                if (in_array($field['type'], ['replicator', 'bard'])) {
                    if (isset($field['sets'])) {
                        foreach ($field['sets'] as $setKey => $set) {
                            if (isset($set['fields'])) {
                                $result[$key][$setKey] = $this->getTranslatableSetKeys($set['fields']);
                            }
                        }
                    }
                } elseif (isset($field['fields'])) {
                    $result[$key] = $this->getTranslatableSetKeys($field['fields']);
                } else {
                    $result[$key] = $key;
                }
            } elseif (is_array($field)) {
                foreach ($field as $nestedKey => $nestedField) {
                    if (isset($nestedField['type']) && in_array($nestedField['type'], ['replicator', 'bard'])) {
                        if (isset($nestedField['sets'])) {
                            foreach ($nestedField['sets'] as $setKey => $set) {
                                if (isset($set['fields'])) {
                                    $result[$nestedKey][$setKey] = $this->getTranslatableSetKeys($set['fields']);
                                }
                            }
                        }
                    } elseif (isset($nestedField['fields'])) {
                        $result[$nestedKey] = $this->getTranslatableSetKeys($nestedField['fields']);
                    } else {
                        $result[$nestedKey] = $nestedKey;
                    }
                }
            }
        }

        return $result;
    }


    private function translateData(): void
    {
        $this->translatedData = $this->arrayMapRecursive(
            $this->dataToTranslate,
            function ($value, $key) {
                return $this->translate($value, $key);
            }
        );

    }

    private function translate($value, string $key)
    {


        // Check if '$key: $value' should be translated.
        if (!$this->isTranslatableKeyValuePair($value, $key)) {
            return $value;
        }

        // Translate HTML

        if ($this->isHtml($value)) {
            return $this->translateWithDeepL($value, 'html');
        }

        // Translate text
        return $this->translateWithDeepL($value, 'text');
    }

    private function translateWithDeepL(string $text, string $format): string
    {
        $hasSpace = substr($text, -1) === ' ';


        $postData = [
            'auth_key' => $this->apiKeyPrivate,
            'text' => $text,
            'target_lang' => $this->language,
            'formality' => $this->formality,
            'model_type' => 'quality_optimized',
            'preserve_formatting' => (int)$this->preserveFormatting,
            'tag_handling' => 'html', // split_sentences is ignored when model is set to 'quality_optimized', but 'nonewlines' will be used if tag_handling is enabled; 0 will be used if tag_handling is not enabled
        ];

        if ($this->sourceLang) {
            $postData['source_lang'] = $this->sourceLang;
        }

        if ($this->glossaryId) {
            $postData['glossary_id'] = $this->glossaryId;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        Log::debug('DeepL Translation Result', [
            'request' => $postData,
            'result' => $result,
        ]);

        if (isset($result['translations'][0]['text'])) {
            $translatedText = $result['translations'][0]['text'];

            if ($hasSpace) {
                $translatedText .= ' ';
            }

            return $translatedText;
        } else {
            return $text;
        }
    }


    private function isTranslatableKeyValuePair($value, string $key): bool
    {


        // Skip empty $value.
        if (empty($value)) {
            return false;
        }

        // Skip numeric $value.
        if (is_numeric($value)) {
            return false;
        }
        // temp solution for not translating taxonomies
        if (is_numeric($key)) {
            return false;
        }

        // Skip boolean $value.
        if (is_bool($value)) {
            return false;
        }

        // Skip 'type: $value', where $value is a Bard/Replicator set key.
        if ($key === 'type' && $this->arrayKeyExistsRecursive($value, $this->fieldKeys['setKeys'])) {
            return false;
        }

        // Skip if $key doesn't exists in the fieldset.
        if (!$this->arrayKeyExistsRecursive($key, $this->fieldKeys['allKeys']) && !is_numeric($key)) {
            return false;
        }

        if (in_array($key, $this->excludedFields)) {
            return false;
        }

        // Skip if $value is in the target locale.
        // if ($this->service->detectLanguage($value) === $this->targetLocale) {
        //     return false;
        // }

        if (in_array($key, $this->excludedFields)) {
            return false;
        }


        return true;
    }

    private function saveTranslation(): void
    {

        foreach ($this->translatedData as $key => $value) {

            $this->content->set($key, $value);

        }


        $this->content->save();


    }

    private function arrayFilterRecursive(array $array, callable $callback = null): array
    {
        $array = is_callable($callback) ? array_filter($array, $callback) : array_filter($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = $this->arrayFilterRecursive($value, $callback);
            }
        }

        return $array;
    }

    private function translateSlug(): void
    {
        $slug = $this->content->slug();
        $translatedSlug = $this->translateWithDeepL($slug, 'text');


        $this->content->slug($translatedSlug);
    }

    /**
     * Recursively check if a key exists in an array.
     *
     * @param mixed $key
     * @param array $array
     * @return bool
     */
    private function arrayKeyExistsRecursive($key, array $array): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        } else {
            foreach ($array as $nested) {
                if (is_array($nested) && $this->arrayKeyExistsRecursive($key, $nested)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Recursively map an array to a callback function.
     *
     * @param array $array
     * @param callable $callback
     * @return array
     */
    private function arrayMapRecursive(array $array, callable $callback): array
    {
        $output = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $output[$key] = $this->arrayMapRecursive($value, $callback);
            } else {
                $output[$key] = $callback($value, $key);
            }
        }

        return $output;
    }

    /**
     * Check if the provided string is HTML or not.
     *
     * @param string $string
     * @return bool
     */
    private function isHtml(string $string): bool
    {
        return $string != strip_tags($string);
    }

    private function toAllowedLocale(string $input): ?string
    {
        $transformed = strtoupper(str_replace('_', '-', $input));

        // Exact match
        if (in_array($transformed, self::DEEPL_LOCALES)) {
            return $transformed;
        }

        // Try language only
        $lang = strtoupper(substr($input, 0, 2));
        foreach (self::DEEPL_LOCALES as $entry) {
            if ($entry === $lang) {
                return $entry;
            }
        }

        return null; // Not found
    }
}