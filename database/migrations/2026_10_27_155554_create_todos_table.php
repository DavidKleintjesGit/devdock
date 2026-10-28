<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('todos', function (Blueprint $table) {
			$table->id();
			$table->string('title');
			$table->text('note')->nullable();

			// een to-do hoeft nergens aan te hangen, maar mag bij een project horen
			$table->string('project')->nullable();
			$table->timestamp('done_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('todos');
	}
};
