<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('tickets', function (Blueprint $table) {
			$table->id();
			$table->string('key')->unique();
			$table->string('summary');
			$table->string('status')->nullable();
			$table->string('type')->nullable();
			$table->string('priority')->nullable();
			$table->string('url')->nullable();
			$table->timestamp('changed_at')->nullable();
			$table->timestamps();

			$table->index('status');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('tickets');
	}
};
