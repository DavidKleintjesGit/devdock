<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('prompt_runs', function (Blueprint $table) {
			$table->id();
			$table->string('card');
			$table->string('project')->nullable();
			$table->string('branch')->nullable();
			$table->string('ticket_key')->nullable();

			// leeg bij een nieuwe sessie; daarna de sessie die Claude teruggaf
			$table->uuid('session_uuid')->nullable();
			$table->text('prompt');
			$table->string('status')->default('running');
			$table->longText('output')->nullable();
			$table->string('message')->nullable();
			$table->decimal('cost', 8, 4)->nullable();
			$table->timestamp('started_at')->nullable();
			$table->timestamp('finished_at')->nullable();
			$table->timestamps();

			$table->index('card');
			$table->index('status');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('prompt_runs');
	}
};
