<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('prompt_runs', function (Blueprint $table): void {
			// de ronde waarvan deze beurt de punten bijwerkt; staat hij gevuld,
			// dan hoort de beurt bij de lus en niet bij een vraag van jou
			$table->unsignedBigInteger('review_round_id')->nullable()->after('card');
		});
	}

	public function down(): void
	{
		Schema::table('prompt_runs', function (Blueprint $table): void {
			$table->dropColumn('review_round_id');
		});
	}
};
