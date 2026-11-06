<?php

declare(strict_types=1);

use App\Http\Controllers\AgentAssetController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LoginController;
use App\Livewire\Access;
use App\Livewire\Board;
use App\Livewire\Rules;
use App\Livewire\Sessions;
use App\Livewire\Skills;
use App\Livewire\Workflows;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
	Route::get('/inloggen', [LoginController::class, 'create'])->name('login');
	Route::post('/inloggen', [LoginController::class, 'store'])
		->middleware('throttle:20,1')
		->name('login.store');
});

Route::get('/taal/{locale}', [LocaleController::class, 'update'])->name('locale');

Route::middleware('auth')->group(function (): void {
	Route::get('/', Board::class)->name('board');

	Route::get('/sessies', Sessions::class)->name('sessions');
	Route::get('/workflows', Workflows::class)->name('workflows');
	Route::get('/regels', Rules::class)->name('rules');
	Route::get('/skills', Skills::class)->name('skills');
	Route::get('/toegang', Access::class)->name('access');

	Route::delete('/inloggen', [LoginController::class, 'destroy'])->name('logout');
	Route::delete('/apparaten', [DeviceController::class, 'destroy'])->name('devices.destroy');

	Route::get('/agent/{run}/public/{path}', [AgentAssetController::class, 'show'])
		->where(['run' => '[0-9]+', 'path' => '.*'])
		->name('agent.asset');

	Route::post('/bijlagen', [AttachmentController::class, 'store'])->name('attachment.store');
	Route::get('/bijlagen/{path}', [AttachmentController::class, 'show'])
		->where(['path' => '.*'])
		->name('attachment.show');
});
