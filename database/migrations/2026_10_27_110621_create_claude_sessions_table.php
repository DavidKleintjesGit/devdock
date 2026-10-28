<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('claude_sessions', function (Blueprint $table) {
			$table->id();
			$table->uuid('uuid')->unique();
			$table->string('folder');
			$table->string('project')->nullable();
			$table->string('title')->nullable();
			$table->string('branch')->nullable();
			$table->unsignedInteger('prompt_count')->default(0);
			$table->unsignedBigInteger('bytes')->default(0);
			$table->timestamp('last_active_at')->nullable();

			// grootte en wijzigingstijd samen; verandert die niet, dan hoeft het
			// bestand niet opnieuw regel voor regel gelezen te worden
			$table->string('fingerprint')->nullable();
			$table->timestamps();

			$table->index('folder');
			$table->index('last_active_at');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('claude_sessions');
	}
};
