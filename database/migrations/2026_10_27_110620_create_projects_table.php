<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('projects', function (Blueprint $table) {
			$table->id();
			$table->string('name')->unique();
			$table->string('path');
			$table->string('branch')->nullable();
			$table->unsignedSmallInteger('changed_files')->default(0);
			$table->unsignedSmallInteger('ahead')->default(0);
			$table->unsignedSmallInteger('behind')->default(0);
			$table->string('remote')->nullable();
			$table->timestamp('scanned_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('projects');
	}
};
