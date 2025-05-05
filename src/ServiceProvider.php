<?php

namespace AiTranslator;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Events\Cp\CpRendering;
use Illuminate\Support\Facades\Route;
use AiTranslator\Actions\SelectEntriesToTranslate;
use Statamic\Statamic;
use Statamic\Facades\CP\Nav;
use Illuminate\Support\Facades\File;


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

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    // protected $vite = [
    //     'input' => ['resources/js/cp.js'],
    //     'publicDirectory' => 'dist',
    //     'hotFile' => __DIR__.'/../dist/hot',
    // ];


    public function bootAddon()
    {


        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ai-translator');

        

     

        Nav::extend(function ($nav) {
            $nav->tools('Tools')
                ->name('AI Translator')
                ->route('ai-translator.config.index')
                ->icon('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 5h16M9 3v2m6-2v2M10 9h4m0 0c-.5 2.5-2 5-4 6.5M14 9c.5 2.5 2 5 4 6.5"/>
                    <path d="M5 20h6m-3-3v6"/>
                </svg>');
        });
        

        $envFilePath = base_path('.env');
        $envContents = File::get($envFilePath);

        if (preg_match('/^AI_TRANSLATOR_API_KEY=.*$/m', $envContents)) {
            $envContents .= "\nAI_TRANSLATOR_API_KEY=" . "\n";
            File::put($envFilePath, $envContents);
        }

        $this->publishes([
            __DIR__ . '/../config/ai-translation.php' => config_path('ai-translator.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai-translation.php', 'ai-translator.ai-translator'
        );

       
    }
}
