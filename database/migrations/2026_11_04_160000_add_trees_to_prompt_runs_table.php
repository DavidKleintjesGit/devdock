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
			$table->string('tree_before', 64)->nullable()->after('git_grant');
			$table->string('tree_after', 64)->nullable()->after('tree_before');
		});
	}

	public function down(): void
	{
		Schema::table('prompt_runs', function (Blueprint $table): void {
			$table->dropColumn(['tree_before', 'tree_after']);
		});
	}
};
