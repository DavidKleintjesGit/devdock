<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('task_agents', function (Blueprint $table): void {
			// every agent gets a name of its own, so a conversation is with
			// someone instead of with a key
			$table->string('name')->nullable()->after('session_uuid');
		});
	}

	public function down(): void
	{
		Schema::table('task_agents', function (Blueprint $table): void {
			$table->dropColumn('name');
		});
	}
};
