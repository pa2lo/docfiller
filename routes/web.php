<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\AddressController;

Route::middleware('auth')->group(function () {
	Route::get('/', [DocumentController::class, 'index'])->name('dashboard');
	Route::get('/documents', fn () => to_route('dashboard'));
	Route::post('/documents', [DocumentController::class, 'store']);
	Route::get('/documents/{document}', [DocumentController::class, 'edit']);
	Route::get('/documents/{document}/read', [DocumentController::class, 'findVariables']);
	Route::patch('/documents/{document}', [DocumentController::class, 'update']);
	Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
	Route::post('/documents/{document}/updateFields', [DocumentController::class, 'updateFields']);
	Route::get('/documents/{document}/download', [DocumentController::class, 'downloadDocument']);
	Route::get('/documents/{document}/getXLSXTemplate', [DocumentController::class, 'getXLSXTemplate']);
	Route::post('/getXLSXTemplate', [DocumentController::class, 'getXLSXTemplateWData']);

	Route::get('/fills', [DocumentController::class, 'fills']);
	Route::post('/documents/{document}/fill', [DocumentController::class, 'fill']);
	Route::patch('/fill/{fill}', [DocumentController::class, 'updateFill']);
	Route::delete('/fill/{fill}', [DocumentController::class, 'destroyFill']);
	Route::get('/fill/{fill}/download', [DocumentController::class, 'downloadFill']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);

	Route::get('/settings', [DocumentController::class, 'settings']);
	Route::post('/settings/addField', [DocumentController::class, 'addField']);
	Route::post('/settings/deleteField', [DocumentController::class, 'deleteField']);

	Route::get('/address-book', [AddressController::class, 'index'])->name('addresses');
	Route::get('/address-book/getData', [AddressController::class, 'getData']);
	Route::post('/address-book', [AddressController::class, 'store']);
	Route::patch('/address-book/{address}', [AddressController::class, 'update']);
	Route::delete('/address-book/{address}', [AddressController::class, 'destroy']);

	Route::post('/ruzSuggestions', [DocumentController::class, 'ruzSuggestions']);
	Route::post('/loadRuzData', [DocumentController::class, 'loadRuzData']);
	Route::post('/checkRuzVat', [DocumentController::class, 'checkRuzVat']);

	Route::post('/loadFieldsData', [DocumentController::class, 'loadFieldsData']);

	Route::middleware('isAdmin')->group(function () {
		Route::get('/users', [UsersController::class, 'index'])->name('users');
		Route::post('/users', [UsersController::class, 'store']);
		Route::get('/users/{user}', [UsersController::class, 'edit'])->name('user.edit');
		Route::patch('/users/{user}', [UsersController::class, 'update']);
		Route::delete('/users/{user}', [UsersController::class, 'destroy']);

		// admin routes + cache functions
		Route::prefix('admin')->group(function () {
			Route::get('/', fn() => inertia('Admin'));

			// config cache
			Route::get('/configClear', function() { echo Artisan::call('config:clear'); });
			Route::get('/configCache', function() { echo Artisan::call('config:cache'); });

			// route cache
			Route::get('/routeClear', function() { echo Artisan::call('route:clear'); });
			Route::get('/routeCache', function() { echo Artisan::call('route:cache'); });

			// views cache
			Route::get('/viewClear', function() { echo Artisan::call('view:clear'); });
			Route::get('/viewCache', function() { echo Artisan::call('view:cache');	});

			// link storage
			Route::get('/linkStorage', function() { echo Artisan::call('storage:link'); });
			Route::get('/unlinkStorage', function() { echo Artisan::call('storage:unlink'); });
		});
	});
});

require __DIR__.'/auth.php';
