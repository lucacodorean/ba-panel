<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lucacodorean\BaPanel\Laravel\Http\Controllers\FeasibilityController;
use Lucacodorean\BaPanel\Laravel\Http\Controllers\PanelController;

/*
| The panel routes. The service provider loads this file only when "ba-panel.enabled" is true.
*/
Route::middleware('web')->group(static function (): void {
    Route::get('/ba-panel', [PanelController::class, 'index'])
        ->name('ba-panel');

    // The node id is the only input. The controller never reads a path from the request.
    Route::get('/ba-panel/documents/{node}', [PanelController::class, 'show'])
        ->where('node', '[a-f0-9]{12}')
        ->name('ba-panel.documents');

    Route::get('/ba-panel/search', [PanelController::class, 'search'])
        ->name('ba-panel.search');

    // The request gives node ids only. The controller finds each file through the graph.
    Route::post('/ba-panel/feasibility', [FeasibilityController::class, 'assess'])
        ->name('ba-panel.feasibility');
});
