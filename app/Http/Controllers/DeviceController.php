<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\Account\Actions\LogOutEverywhereAction;

class DeviceController extends Controller
{
	/**
	 * Logs every device out, this one included.
	 */
	public function destroy(Request $request): RedirectResponse
	{
		LogOutEverywhereAction::execute();

		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return redirect()->route('login');
	}
}
