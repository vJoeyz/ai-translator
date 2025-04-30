<?php

namespace AiTranslator;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Events\Cp\CpRendering;
use Illuminate\Support\Facades\Route;
use AiTranslator\Actions\SelectEntriesToTranslate;
use Statamic\Statamic;
use Statamic\Facades\CP\Nav;

class ServiceProvider extends AddonServiceProvider
{
    // protected $vite = [ 
    //     'input' => [
    //         'resources/js/translate-button.js',
    //     ],
    //     'publicDirectory' => 'resources/dist',
    // ];

    protected $actions = [
        SelectEntriesToTranslate::class
    ];


    public function bootAddon()
    {

      
        
        // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        // dump($this->loadRoutesFrom(__DIR__.'/../routes/web.php'));


        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-translator');

       
      
        $this->registerWebRoutes(function () {
            Route::post('/translate', 'AiTranslator\TranslateController@index');
            Route::get('/pagetranslator-settings', 'AiTranslator\SettingsController@index')->name('statamic.cp.ai-translator.pagetranslator.settings');  // Geef de juiste naam aan de route
            Route::post('/pagetranslator-settings-save', 'AiTranslator\SettingsController@save')->name('statamic.cp.ai-translator.pagetranslator.settings.save');  // Geef de juiste naam aan de route


        });

        Nav::extend(function ($nav) {
            $nav->tools('Tools') // Hiermee geef je aan waar je het item wilt plaatsen (bijvoorbeeld onder 'Tools')
                ->name('AI Translator') // De naam van het menu-item
                ->route('ai-translator.pagetranslator.settings') // Gebruik de juiste route naam
                ->icon('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M6.547 9.674l7.778 7.778a4.363 4.363 0 0 0 .9-4.435l5.965-5.964.177.176a1.25 1.25 0 0 0 1.768-1.767l-4.6-4.6a1.25 1.25 0 0 0-1.765 1.771l.177.177-5.965 5.965a4.366 4.366 0 0 0-4.435.899zM10.436 13.563L.5 23.499" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>'); // SVG-icoon
        });


        $this->publishes([
            __DIR__ . '/../config/ai-translation.php' => config_path('ai-translator.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai-translation.php', 'ai-translator.ai-translator'
        );

        // if ($this->app->runningInConsole()) {
        //     $this->commands([
        //         TranslateContentCommand::class,
        //     ]);
        // }
    }
}
