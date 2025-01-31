<?php

namespace AiTranslator\Actions;

use Statamic\Actions\Action;
use Statamic\Facades\Site;
use AiTranslator\TranslateController;

class SelectEntriesToTranslate extends Action
{
    public static function title()
    {
        return __('Translate');
    }

    protected function fieldItems()
    {
        $currentSite = Site::current()->handle();
        
        $sites = Site::all()->filter(fn($site) => $site->handle() !== $currentSite);

        return [
            'language' => [
                'type' => 'select',
                'label' => __('Pick a language'),
                'options' => $sites->mapWithKeys(function ($site) {
                    return [$site->locale => $site->locale];
                })->toArray(),
                'default' => $sites->first()->locale, 
            ]
        ];
    }

    public function run($items, $values)
    {
        $currentSite = Site::current()->handle();
        
        $sites = Site::all()->filter(fn($site) => $site->handle() !== $currentSite);

        $chosenLanguage = $values['language']; 

        $siteData = $sites->firstWhere('locale', $chosenLanguage);

        if ($siteData) {
            $controller = new TranslateController();
            $controller->index($items, $siteData);
        } else {
            return __('Something went wrong');
        }
    }
}
