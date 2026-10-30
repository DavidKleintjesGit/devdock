<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('lenses', function (Blueprint $table): void {
			$table->id();
			$table->string('name')->unique();
			$table->string('description');
			$table->json('paths');
			$table->string('command')->nullable();
			$table->unsignedInteger('position')->default(0);
			$table->timestamps();
		});

		// a lens is on everywhere until a row here says otherwise for one project
		Schema::create('lens_settings', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('lens_id')->constrained()->cascadeOnDelete();
			$table->string('project');
			$table->boolean('is_enabled')->default(true);
			$table->timestamps();
			$table->unique(['lens_id', 'project']);
		});

		Schema::create('review_rounds', function (Blueprint $table): void {
			$table->id();
			$table->string('card')->index();
			$table->unsignedInteger('number');
			$table->string('commit_sha')->nullable();
			$table->string('status');
			$table->timestamp('started_at')->nullable();
			$table->timestamp('finished_at')->nullable();
			$table->timestamps();
			$table->unique(['card', 'number']);
		});

		Schema::create('review_results', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('review_round_id')->constrained()->cascadeOnDelete();
			$table->foreignId('lens_id')->constrained()->cascadeOnDelete();
			$table->string('verdict');
			$table->unsignedInteger('findings')->default(0);
			$table->text('report')->nullable();
			$table->unsignedInteger('touched_files')->default(0);
			$table->uuid('session_uuid')->nullable();
			$table->timestamp('finished_at')->nullable();
			$table->timestamps();
			$table->unique(['review_round_id', 'lens_id']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('review_results');
		Schema::dropIfExists('review_rounds');
		Schema::dropIfExists('lens_settings');
		Schema::dropIfExists('lenses');
	}
};
