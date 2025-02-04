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
        $currentSite = Site::selected()->handle();
   
        // dump(Site::selected());
        // // $sitesTest = Site::all();
        // // dump($sitesTest);
        // die();
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
        $currentSite = Site::selected()->handle();
       
        
        $sites = Site::all()->filter(fn($site) => $site->handle() !== $currentSite);

        $chosenLanguage = $values['language']; 

        $siteData = $sites->firstWhere('locale', $chosenLanguage);
        $shortLocale = $siteData->short_locale;
       
        if ($siteData) {
            $controller = new TranslateController();
            $controller->index($items, $siteData, $shortLocale);
        } else {
            return __('Something went wrong');
        }
    }
}
