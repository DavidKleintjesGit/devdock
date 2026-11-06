<?php

declare(strict_types=1);

namespace Src\Account\Repositories;

use App\Models\User as UserEloquent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Src\Account\Contracts\AccountRepositoryContract;
use Src\Account\Objects\AccountEntity;
use Src\Account\Objects\AccountId;
use Src\Account\Objects\PasswordObject;

class AccountRepository implements AccountRepositoryContract
{
	/**
	 * The name and address the one account is stored under; nobody types them.
	 */
	private const NAME = 'DevDock';

	private const EMAIL = 'owner@devdock.local';

	public function getOwner(): ?AccountEntity
	{
		$userEloquent = UserEloquent::query()->orderBy('id')->first();

		return $userEloquent === null ? null : $this->mapToEntity($userEloquent);
	}

	public function passwordMatches(AccountId $accountId, string $password): bool
	{
		$userEloquent = UserEloquent::query()->find($accountId->id);

		return $userEloquent !== null && Hash::check($password, $userEloquent->password);
	}

	public function savePassword(PasswordObject $passwordObject): AccountEntity
	{
		$userEloquent = UserEloquent::query()->orderBy('id')->first()
			?? new UserEloquent(['name' => self::NAME, 'email' => self::EMAIL]);

		$userEloquent->password = $passwordObject->password;
		$userEloquent->save();

		return $this->mapToEntity($userEloquent);
	}

	public function forgetDevices(AccountId $accountId): void
	{
		UserEloquent::query()
			->whereKey($accountId->id)
			->update(['remember_token' => Str::random(60)]);

		DB::table((string) config('session.table'))
			->where('user_id', $accountId->id)
			->delete();
	}

	private function mapToEntity(UserEloquent $userEloquent): AccountEntity
	{
		return new AccountEntity(
			accountId: new AccountId(id: (int) $userEloquent->id),
			name: (string) $userEloquent->name,
		);
	}
}
