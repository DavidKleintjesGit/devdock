<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
	/**
	 * The language DevDock speaks, for this browser.
	 */
	public function update(string $locale): RedirectResponse
	{
		session(['locale' => in_array($locale, ['nl', 'en'], true) ? $locale : 'en']);

		return back();
	}
}
