<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('skills', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->string('scope');
			$table->string('project')->nullable();
			$table->string('plugin')->nullable();
			$table->text('description')->nullable();
			$table->string('path');
			$table->unsignedInteger('lines')->default(0);
			$table->timestamp('scanned_at')->nullable();
			$table->timestamps();

			$table->unique('path');
			$table->index('scope');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('skills');
	}
};
