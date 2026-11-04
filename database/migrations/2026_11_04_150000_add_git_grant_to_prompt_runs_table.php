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
			$table->string('git_grant')->nullable()->after('claims_session');
		});
	}

	public function down(): void
	{
		Schema::table('prompt_runs', function (Blueprint $table): void {
			$table->dropColumn('git_grant');
		});
	}
};
