<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('libraries', function (Blueprint $table): void {
			$table->id();
			$table->string('name')->unique();

			// a folder whose changes come in as new versions; DevDock never
			// writes there on its own
			$table->string('folder')->nullable();
			$table->string('skills_folder')->nullable();
			$table->timestamps();
		});

		Schema::create('library_items', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('library_id')->constrained()->cascadeOnDelete();
			$table->string('kind');
			$table->string('name');
			$table->timestamps();

			$table->unique(['library_id', 'kind', 'name']);
		});

		Schema::create('library_versions', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('library_item_id')->constrained()->cascadeOnDelete();
			$table->unsignedInteger('number');

			// the whole content of every version, so any of them can come back
			$table->longText('contents');
			$table->string('hash', 40);
			$table->string('source');
			$table->string('note')->nullable();
			$table->string('project')->nullable();
			$table->timestamp('created_at')->nullable();

			$table->unique(['library_item_id', 'number']);
			$table->index('hash');
		});

		Schema::create('library_followers', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('library_id')->constrained()->cascadeOnDelete();
			$table->string('project');
			$table->timestamps();

			$table->unique(['library_id', 'project']);
		});

		Schema::create('library_rollouts', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('library_version_id')->constrained()->cascadeOnDelete();
			$table->string('project');
			$table->string('path');

			// what stood there before, so a roll-out can always be undone
			$table->longText('backup')->nullable();
			$table->timestamp('created_at')->nullable();
		});

		Schema::create('rule_landings', function (Blueprint $table): void {
			$table->id();
			$table->string('project')->unique();
			$table->string('kind');
			$table->string('path')->nullable();
			$table->timestamps();
		});

		Schema::create('favorites', function (Blueprint $table): void {
			$table->id();
			$table->string('kind');
			$table->string('project');
			$table->string('path');
			$table->timestamps();

			$table->unique(['kind', 'project', 'path']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('favorites');
		Schema::dropIfExists('rule_landings');
		Schema::dropIfExists('library_rollouts');
		Schema::dropIfExists('library_followers');
		Schema::dropIfExists('library_versions');
		Schema::dropIfExists('library_items');
		Schema::dropIfExists('libraries');
	}
};
