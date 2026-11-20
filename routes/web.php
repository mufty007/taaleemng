<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\AppEntryController;
use App\Http\Controllers\Marketing\DemoRequestController;
use App\Http\Controllers\Marketing\MarketingController;
use App\Http\Controllers\Platform;
use App\Http\Controllers\School;
use App\Http\Controllers\SchoolPickerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing site
|--------------------------------------------------------------------------
*/

Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::get('/contact', [MarketingController::class, 'contact'])->name('contact');
Route::get('/privacy', [MarketingController::class, 'legal'])->defaults('page', 'privacy')->name('privacy');
Route::get('/terms', [MarketingController::class, 'legal'])->defaults('page', 'terms')->name('terms');
Route::post('/demo-requests', [DemoRequestController::class, 'store'])
    ->middleware('throttle:demo-requests')
    ->name('demo-requests.store');

/*
|--------------------------------------------------------------------------
| Signed-in area
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/app', AppEntryController::class)->name('app');
    Route::get('/app/schools', SchoolPickerController::class)->name('schools.pick');
    Route::get('/account', [AccountController::class, 'edit'])->name('account');

    // Everything inside a school. {school} is the school's slug; see EnsureSchoolContext.
    Route::prefix('app/{school}')
        ->middleware(['school', 'school.active'])
        ->name('school.')
        ->group(function () {
            Route::get('/', School\DashboardController::class)->name('dashboard');
        });

    Route::prefix('platform')
        ->middleware('platform.admin')
        ->name('platform.')
        ->group(function () {
            Route::get('/', Platform\DashboardController::class)->name('dashboard');
            Route::get('/schools', [Platform\SchoolController::class, 'index'])->name('schools.index');
            Route::get('/schools/create', [Platform\SchoolController::class, 'create'])->name('schools.create');
            Route::post('/schools', [Platform\SchoolController::class, 'store'])->name('schools.store');
            Route::get('/leads', [Platform\DemoRequestController::class, 'index'])->name('leads.index');
            Route::patch('/leads/{demoRequest}', [Platform\DemoRequestController::class, 'update'])->name('leads.update');
        });
});
