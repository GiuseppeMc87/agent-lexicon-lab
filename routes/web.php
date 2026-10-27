<?php

use App\Http\Controllers\ExperimentController;
use App\Http\Controllers\ExperimentUpdateController;
use App\Http\Controllers\RunExperimentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'experiments')->name('dashboard');
    Route::resource('experiments', ExperimentController::class)
        ->only(['index', 'store', 'show']);
    Route::post('experiments/{experiment}/run', RunExperimentController::class)
        ->middleware('throttle:experiment-start')
        ->name('experiments.run');
    Route::get('experiments/{experiment}/updates', ExperimentUpdateController::class)
        ->middleware('throttle:experiment-poll')
        ->name('experiments.updates');
});

require __DIR__.'/settings.php';
