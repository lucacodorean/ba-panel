<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/*
| The panel entry point. The service provider loads this file only when "ba-panel.enabled" is true.
*/
Route::middleware('web')
    ->get('/ba-panel', static fn (): View => view('ba-panel::panel'))
    ->name('ba-panel');
