<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('task_links', function (Blueprint $table): void {
			// many tasks work in the same project; only a ticket, branch or pull request belongs to one task
			$table->dropUnique(['kind', 'value']);
			$table->unique(['task_id', 'kind', 'value']);
		});
	}

	public function down(): void
	{
		Schema::table('task_links', function (Blueprint $table): void {
			$table->dropUnique(['task_id', 'kind', 'value']);
			$table->unique(['kind', 'value']);
		});
	}
};
