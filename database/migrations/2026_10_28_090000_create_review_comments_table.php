<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('review_comments', function (Blueprint $table): void {
			$table->id();
			$table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
			$table->unsignedBigInteger('comment_id')->unique();
			$table->string('author')->nullable();
			$table->text('body');
			$table->string('path')->nullable();
			$table->unsignedInteger('line')->nullable();
			$table->boolean('is_prompt')->default(false);
			$table->string('url')->nullable();
			$table->timestamp('commented_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('review_comments');
	}
};
