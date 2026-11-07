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
    Route::livewire('/plans/{id}', Livewire\PlanDetailPage::class)->name('plans.show');
    Route::livewire('/manage/plans', Livewire\Manage\PlansPage::class)->name('manage.plans');
    Route::livewire('/manage/reviews', Livewire\Manage\ReviewsPage::class)->name('manage.reviews');
    Route::livewire('/manage/insurers', Livewire\Manage\InsurersPage::class)->name('manage.insurers');
    Route::livewire('/manage/users', Livewire\Manage\UsersPage::class)->name('manage.users');
    Route::livewire('/manage/audit', Livewire\Manage\AuditPage::class)->name('manage.audit');
    Route::get('/documents/{filename}', [DocumentController::class, 'show'])->name('documents.show');
});
