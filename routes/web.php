<?php

use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/map-data', [DashboardController::class, 'mapData'])->name('dashboard.map-data');

    Route::get('/shipments', [PortalController::class, 'shipments'])->name('shipments.index');
    Route::get('/shipments/create', [PortalController::class, 'createShipment'])
        ->middleware('role:admin,operator')
        ->name('shipments.create');
    Route::post('/shipments', [PortalController::class, 'storeShipment'])
        ->middleware('role:admin,operator')
        ->name('shipments.store');
    Route::get('/shipments/{shipment}', [PortalController::class, 'showShipment'])->name('shipments.show');
    Route::put('/shipments/{shipment}', [PortalController::class, 'updateShipment'])
        ->middleware('role:admin,operator')
        ->name('shipments.update');
    Route::post('/shipments/{shipment}/tracking-events', [PortalController::class, 'storeTrackingEvent'])
        ->middleware('role:admin,operator')
        ->name('shipments.tracking-events.store');
    Route::get('/tracking/{shipment}', [PortalController::class, 'showShipment'])->name('tracking.show');

    Route::get('/map', [PortalController::class, 'map'])->name('map');
    Route::get('/exceptions', [PortalController::class, 'exceptions'])->name('exceptions.index');
    Route::get('/reports', [PortalController::class, 'reports'])->name('reports.index');
    Route::get('/analytics', [PortalController::class, 'reports'])->name('analytics.index');
    Route::get('/news', [PortalController::class, 'news'])->name('news.index');
    Route::get('/news/articles', [PortalController::class, 'newsArticles'])->name('news.articles');
    Route::get('/news/latest', [PortalController::class, 'latestNewsArticles'])->name('news.latest');
    Route::get('/news/{event}', [PortalController::class, 'showNews'])->name('news.show');
    Route::get('/users', [PortalController::class, 'users'])
        ->middleware('role:admin')
        ->name('users.index');
    Route::get('/settings', [PortalController::class, 'settings'])->name('settings.index');
    Route::put('/settings', [PortalController::class, 'updateSettings'])->name('settings.update');
});
