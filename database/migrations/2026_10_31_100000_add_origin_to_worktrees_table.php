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
			// whether DevDock made this folder, found it already standing, or works in the project itself
			$table->string('origin')->default('made')->after('path');

			// a branch DevDock created goes again once it is merged; one it found stays
			$table->boolean('branch_created')->default(false)->after('branch');
		});
	}

	public function down(): void
	{
		Schema::table('worktrees', function (Blueprint $table): void {
			$table->dropColumn(['origin', 'branch_created']);
		});
	}
};
