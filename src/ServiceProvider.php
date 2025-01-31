<?php

namespace AiTranslator;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Events\Cp\CpRendering;
use Illuminate\Support\Facades\Route;
use AiTranslator\Actions\SelectEntriesToTranslate;

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
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->registerWebRoutes(function () {
            Route::post('/translate', 'AiTranslator\TranslateController@index');
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
