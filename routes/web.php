<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Livewire\Board;
use App\Livewire\Rules;
use App\Livewire\Sessions;
use App\Livewire\Skills;
use Illuminate\Support\Facades\Route;

Route::get('/', Board::class)->name('board');

Route::get('/sessies', Sessions::class)->name('sessions');
Route::view('/workflows', 'pages.placeholder', ['page' => 'Workflows', 'icon' => 'workflows'])->name('workflows');
Route::get('/regels', Rules::class)->name('rules');
Route::get('/skills', Skills::class)->name('skills');

Route::get('/taal/{locale}', [LocaleController::class, 'update'])->name('locale');
