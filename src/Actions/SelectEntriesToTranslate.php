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
        return $this->isMultisite() && $item instanceof \Statamic\Entries\Entry;
    }
    
    public function visibleToBulk($items)
    {
        return $this->isMultisite() && collect($items)->every(fn ($item) => $item instanceof \Statamic\Entries\Entry);
    }
    

    protected function fieldItems()
    {
        $run = $this->isMultisite();
        if($run) {
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
       
    }

    public function run($items, $values)
    {
        $run = $this->isMultisite();
        if(!$run) {
            return __('This action is not available for single site installations.');
        }

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

    private function isMultisite()
    {   
        return Site::all()->count() > 1;
    }
}
