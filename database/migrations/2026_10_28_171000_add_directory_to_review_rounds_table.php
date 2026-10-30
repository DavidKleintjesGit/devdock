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
			$table->string('directory')->nullable()->after('commit_sha');
		});
	}

	public function down(): void
	{
		Schema::table('review_rounds', function (Blueprint $table): void {
			$table->dropColumn('directory');
		});
	}
};
