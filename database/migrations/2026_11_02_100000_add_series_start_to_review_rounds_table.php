<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('review_rounds', function (Blueprint $table): void {
			// the number of the round a loop started on; restarting the loop starts a new series
			$table->unsignedInteger('series_start')->nullable()->after('number');
		});
	}

	public function down(): void
	{
		Schema::table('review_rounds', function (Blueprint $table): void {
			$table->dropColumn('series_start');
		});
	}
};
