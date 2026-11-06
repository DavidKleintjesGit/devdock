<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
	public function rules(): array
	{
		return [
			'password' => ['required', 'string'],
			'remember' => ['nullable', 'boolean'],
		];
	}
}
