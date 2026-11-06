<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Src\Account\Actions\GetLoginStateAction;
use Src\Account\Actions\LogInAction;
use Src\Account\Actions\LogOutAction;
use Src\Account\Enums\LoginOutcomeEnum;
use Src\Account\Objects\LoginObject;

class LoginController extends Controller
{
	public function create(Request $request): View
	{
		return view('auth.login', [
			'loginStateObject' => GetLoginStateAction::execute($request->session()->get('url.intended')),
		]);
	}

	public function store(LoginRequest $request): RedirectResponse
	{
		$loginResultObject = LogInAction::execute(LoginObject::from($request->validated()));

		if ($loginResultObject->loginOutcome !== LoginOutcomeEnum::LOGGED_IN) {
			return back()->withErrors(['password' => $loginResultObject->message()]);
		}

		$request->session()->regenerate();

		return redirect()->intended(route('board'));
	}

	public function destroy(Request $request): RedirectResponse
	{
		LogOutAction::execute();

		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return redirect()->route('login');
	}
}
