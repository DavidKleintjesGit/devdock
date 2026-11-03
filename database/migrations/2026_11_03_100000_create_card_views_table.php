<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('card_views', function (Blueprint $table): void {
			$table->id();
			$table->string('card')->unique();
			$table->timestamp('seen_at');
			$table->timestamps();
		});

		// everything that happened before the badges existed counts as read
		DB::table('card_views')->insert([
			'card' => '*',
			'seen_at' => CarbonImmutable::now(),
			'created_at' => CarbonImmutable::now(),
			'updated_at' => CarbonImmutable::now(),
		]);
	}

	public function down(): void
	{
		Schema::dropIfExists('card_views');
	}
};
