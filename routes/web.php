<?php

use Illuminate\Support\Facades\Route;
use AiTranslator\TranslateController as PageTranslatorController;
use AiTranslator\SettingsController;


Route::post('/ai-translator/translate', [PageTranslatorController::class, 'index'])
    ->name('ai-translator.index');

