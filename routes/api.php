<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // routes go here as each phase adds them
    Route::post('/test', []);
});
