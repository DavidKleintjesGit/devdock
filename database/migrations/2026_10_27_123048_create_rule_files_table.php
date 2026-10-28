<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('rule_files', function (Blueprint $table) {
			$table->id();
			$table->string('project');
			$table->string('path');
			$table->string('group');
			$table->string('origin');
			$table->unsignedInteger('lines')->default(0);
			$table->unsignedInteger('words')->default(0);
			$table->unsignedInteger('bytes')->default(0);

			// de sleutel van het tegenhanger-bestand in mtvtd-core, leeg als er geen is
			$table->string('core_file')->nullable();
			$table->unsignedInteger('core_lines')->nullable();
			$table->timestamp('scanned_at')->nullable();
			$table->timestamps();

			$table->unique(['project', 'path']);
			$table->index('origin');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('rule_files');
	}
};
