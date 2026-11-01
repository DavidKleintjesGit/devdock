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
			// a database DevDock created for this copy; it goes when the copy goes
			$table->string('database')->nullable()->after('site');
		});
	}

	public function down(): void
	{
		Schema::table('worktrees', function (Blueprint $table): void {
			$table->dropColumn('database');
		});
	}
};
