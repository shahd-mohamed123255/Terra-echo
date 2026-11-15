<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\FarmWizardController;
use App\Http\Controllers\MyFarmController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SimulationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'))->name('home');
Route::view('/about', 'about')->name('about');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.auth', ['mode' => 'login']))->name('login');
    Route::get('/register', fn () => view('auth.auth', ['mode' => 'register']))->name('register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.submit');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/my-farm', [MyFarmController::class, 'index'])->name('farm.index');
    Route::post('/my-farm/plots/{fieldId}/crop', [MyFarmController::class, 'assignCrop'])
        ->name('farm.plots.crop.assign');
    Route::delete('/my-farm/plots/{fieldId}/crop', [MyFarmController::class, 'removeCrop'])
        ->name('farm.plots.crop.remove');
    Route::post('/my-farm/farms/{farm}/simulations', [SimulationController::class, 'store'])
        ->name('farm.simulations.store');
    Route::post('/my-farm/simulations/{simulation}/run', [SimulationController::class, 'run'])
        ->name('farm.simulations.run');
    Route::get('/my-farm/simulations/{simulation}', [SimulationController::class, 'show'])
        ->name('farm.simulations.show');
    Route::post('/my-farm/simulations/{simulation}/cancel', [SimulationController::class, 'cancel'])
        ->name('farm.simulations.cancel');

    Route::get('/explore-farm', [FarmController::class, 'explore'])->name('farm.explore');
    Route::post('/explore-farm', [FarmController::class, 'storeLocation'])->name('farm.location.store');
    Route::get('/plans', [PlanController::class, 'plans'])->name('plans');
    Route::post('/plans', [PlanController::class, 'selectPlan'])->name('plans.select');
});

Route::middleware('auth')->prefix('farm')->name('farm.')->group(function () {
    Route::get('details', [FarmWizardController::class, 'details'])->name('details');
    Route::post('details', [FarmWizardController::class, 'saveDetails'])->name('details.save');
    Route::get('water', [FarmWizardController::class, 'water'])->name('water');
    Route::post('water', [FarmWizardController::class, 'saveWater'])->name('water.save');
    Route::get('summary', [FarmWizardController::class, 'summary'])->name('summary');
    Route::post('summary', [FarmWizardController::class, 'confirm'])->name('confirm');
});

Route::middleware('auth')->prefix('chat')->group(function () {
    Route::post('/start', [ChatController::class, 'start'])->name('chat.start');
    Route::post('/message', [ChatController::class, 'message'])->name('chat.message');
});
