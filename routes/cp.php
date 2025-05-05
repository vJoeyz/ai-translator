<?php

use Illuminate\Support\Facades\Route;
use AiTranslator\TranslateController as PageTranslatorController;
use AiTranslator\SettingsController;


Route::post('/ai-translator/translate', [PageTranslatorController::class, 'index'])
    ->name('ai-translator.index');


Route::name('ai-translator.')->prefix('ai-translator')->group(function () {
    Route::get('config', [SettingsController::class, 'index'])->name('config.index');
    Route::post('config-edit', [SettingsController::class, 'save'])->name('config.edit');

    // Route::post('config', [Controllers\ConfigController::class, 'update'])->name('config.update');

    // Route::get('form-fields/{form}', [Controllers\GetFormFieldsController::class, '__invoke'])->name('form-fields');
    // Route::get('merge-fields/{list}', [Controllers\GetMergeFieldsController::class, '__invoke'])->name('merge-fields');
    // Route::get('tags/{list}', [Controllers\GetTagsController::class, '__invoke'])->name('tags');
    // Route::get('user-fields', [Controllers\GetUserFieldsController::class, '__invoke'])->name('user-fields');
});
