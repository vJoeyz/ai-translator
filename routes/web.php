<?php

use Illuminate\Support\Facades\Route;
use AiTranslator\TranslateController as PageTranslatorController;
use AiTranslator\SettingsController;


Route::post('/ai-translator/translate', [PageTranslatorController::class, 'index'])
    ->name('ai-translator.index');


// Route::middleware('cp')->prefix('ai-translator')->group(function () {
//     // Instellingen pagina
//     Route::get('settings', [SettingsController::class, 'index'])->name('ai-translator.settings');

//     // Opslaan van instellingen
//     Route::post('settings', [SettingsController::class, 'save'])->name('ai-translator.settings.save');
// });