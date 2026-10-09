<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CrmController;
use App\Http\Middleware\ActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:login')->name('login.submit');
Route::middleware(['auth', ActiveUser::class])->group(function () {
    Route::get('/', [CrmController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/activity', [CrmController::class, 'activity'])->name('activity');
    Route::get('/reports', [CrmController::class, 'reports'])->name('reports');
    Route::get('/admin', [AdminController::class, 'index'])->name('admin');
    Route::post('/admin/users', [AdminController::class, 'user'])->name('admin.users.store');
    Route::put('/admin/users/{id}', [AdminController::class, 'user'])->whereNumber('id')->name('admin.users.update');
    Route::post('/admin/teams', [AdminController::class, 'team'])->name('admin.teams');
    Route::post('/admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
    Route::get('/documents/{document}', [CrmController::class, 'download'])->whereNumber('document')->name('documents.download');
    Route::post('/clients/{client}/contacts', [CrmController::class, 'contact'])->whereNumber('client')->name('contacts.store');
    Route::put('/clients/{client}/contacts/{contact}', [CrmController::class, 'contact'])->whereNumber(['client', 'contact'])->name('contacts.update');
    Route::delete('/clients/{client}/contacts/{contact}', [CrmController::class, 'deleteContact'])->whereNumber(['client', 'contact'])->name('contacts.destroy');
    Route::prefix('/workspace/{entity}')->where(['entity' => 'clients|candidates|jobs|applications|interviews|tasks|placements'])->group(function () {
        Route::get('/', [CrmController::class, 'index'])->name('records.index');
        Route::get('/create', [CrmController::class, 'form'])->name('records.create');
        Route::get('/export', [CrmController::class, 'export'])->name('records.export');
        Route::post('/import', [CrmController::class, 'import'])->name('records.import');
        Route::post('/', [CrmController::class, 'store'])->name('records.store');
        Route::get('/{id}', [CrmController::class, 'show'])->whereNumber('id')->name('records.show');
        Route::get('/{id}/edit', [CrmController::class, 'form'])->whereNumber('id')->name('records.edit');
        Route::put('/{id}', [CrmController::class, 'update'])->whereNumber('id')->name('records.update');
        Route::delete('/{id}', [CrmController::class, 'destroy'])->whereNumber('id')->name('records.destroy');
        Route::patch('/{id}/stage', [CrmController::class, 'stage'])->whereNumber('id')->name('records.stage');
        Route::post('/{id}/notes', [CrmController::class, 'note'])->whereNumber('id')->name('records.notes');
        Route::post('/{id}/documents', [CrmController::class, 'upload'])->whereNumber('id')->name('records.upload');
    });
});
