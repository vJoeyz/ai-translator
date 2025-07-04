<?php

return [
    'ai_translator_service' => 'deepl',
    
    'ai_translator_api_key' => env('AI_TRANSLATION_API_KEY'),

    'ai_translator_api_url' => env('AI_TRANSLATION_API_URL', 'https://api.deepl.com/v2/translate'),

    'ai_translator_source_lang' => env('AI_TRANSLATION_SOURCE_LANG'),

    'ai_translator_formality' => env('AI_TRANSLATION_FORMALITY', 'default'),

    'ai_translator_glossary_id' => env('AI_TRANSLATION_GLOSSARY_ID'),

    'ai_translator_translate_slugs' => env('AI_TRANSLATION_TRANSLATE_SLUGS', false),
];