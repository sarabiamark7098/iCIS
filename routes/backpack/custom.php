<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
//    Route::crud('user', 'UserCrudController');
    Route::crud('beneficiary', 'BeneficiaryCrudController');

    // Excel import routes (must be before Route::crud to avoid conflicts)
    Route::get('import/excel-upload', 'ImportCrudController@showImportForm')->name('import.excel-upload');
    Route::post('import/excel-preview', 'ImportCrudController@previewSheets')->name('import.excel-preview');
    Route::post('import/excel-select-sheet', 'ImportCrudController@selectSheet')->name('import.excel-select-sheet');
    Route::post('import/excel-process', 'ImportCrudController@processImport')->name('import.excel-process');

    Route::crud('import', 'ImportCrudController');
    Route::crud('user', 'UserCrudController');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
