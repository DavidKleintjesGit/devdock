<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('tickets', function (Blueprint $table): void {
			// the active sprint the ticket sits in; empty means the backlog
			$table->string('sprint')->nullable()->after('priority');
			$table->timestamp('sprint_ends_at')->nullable()->after('sprint');
		});

		Schema::table('todos', function (Blueprint $table): void {
			$table->boolean('is_later')->default(false)->after('project');
		});
	}

	public function down(): void
	{
		Schema::table('tickets', function (Blueprint $table): void {
			$table->dropColumn(['sprint', 'sprint_ends_at']);
		});

		Schema::table('todos', function (Blueprint $table): void {
			$table->dropColumn('is_later');
		});
	}
};
