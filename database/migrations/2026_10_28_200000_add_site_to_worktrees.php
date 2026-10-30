<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('worktrees', function (Blueprint $table): void {
			$table->string('site')->nullable()->after('path');
		});
	}

	public function down(): void
	{
		Schema::table('worktrees', function (Blueprint $table): void {
			$table->dropColumn('site');
		});
	}
};
