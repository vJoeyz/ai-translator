<?php

namespace AiTranslator\Actions;

use Statamic\Actions\Action;
use Statamic\Facades\Site;
use AiTranslator\TranslateController;
use Statamic\Facades\Entry;
use Statamic\Facades\Page;



class SelectEntriesToTranslate extends Action
{
    public static function title()
    {
        return __('Translate');
    }

    public function visibleToItem($item)
    {
        return $item instanceof \Statamic\Entries\Entry;
    }
    
    public function visibleToBulk($items)
    {
        return collect($items)->every(fn ($item) => $item instanceof \Statamic\Entries\Entry);
        
    }
    

    protected function fieldItems()
    {
        $currentSite = Site::selected()->handle();
   
        
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
