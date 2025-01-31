<?php

use Illuminate\Support\Facades\Route;
use AiTranslator\TranslateController as PageTranslatorController;

Route::post('/ai-translator/translate', [PageTranslatorController::class, 'index'])
    ->name('ai-translator.index');