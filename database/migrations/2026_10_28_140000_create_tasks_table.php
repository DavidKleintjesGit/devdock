<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('tasks', function (Blueprint $table): void {
			$table->id();
			$table->string('key')->unique();
			$table->string('session_uuid')->nullable();
			$table->timestamps();
		});

		Schema::create('task_links', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('task_id')->constrained()->cascadeOnDelete();
			$table->string('kind');
			$table->string('value');
			$table->timestamps();

			$table->unique(['kind', 'value']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('task_links');
		Schema::dropIfExists('tasks');
	}
};
