<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('worktrees', function (Blueprint $table): void {
			$table->id();
			$table->string('project');
			$table->string('branch');
			$table->string('path');
			$table->string('card')->nullable();
			$table->timestamps();

			$table->unique(['project', 'branch']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('worktrees');
	}
};
