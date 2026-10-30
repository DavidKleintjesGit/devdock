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
			$table->boolean('is_active')->default(false)->after('value');
		});

		Schema::create('task_agents', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('task_id')->constrained()->cascadeOnDelete();
			$table->uuid('session_uuid')->unique();
			$table->string('directory')->nullable();
			$table->boolean('is_active')->default(true);
			$table->timestamp('started_at')->nullable();
			$table->timestamps();
		});

		// a task carried one borrowed session; agents are handed out instead,
		// and what came before stays history
		Schema::table('tasks', function (Blueprint $table): void {
			$table->dropColumn('session_uuid');
		});
	}

	public function down(): void
	{
		Schema::table('tasks', function (Blueprint $table): void {
			$table->uuid('session_uuid')->nullable();
		});

		Schema::dropIfExists('task_agents');

		Schema::table('task_links', function (Blueprint $table): void {
			$table->dropColumn('is_active');
		});
	}
};
