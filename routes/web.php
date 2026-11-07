<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

Route::livewire('/login', Livewire\Login::class)->middleware('guest')->name('login');

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('/', '/search');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/demo/switch/{role}', [AuthController::class, 'switchDemoRole'])->name('demo.switch');

    Route::livewire('/search', Livewire\SearchPage::class)->name('search');
    Route::livewire('/compare', Livewire\ComparePage::class)->name('compare');
});
