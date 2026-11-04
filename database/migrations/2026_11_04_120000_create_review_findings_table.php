<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('review_findings', function (Blueprint $table): void {
			$table->id();
			$table->string('card');
			$table->foreignId('lens_id')->constrained('lenses')->cascadeOnDelete();

			// the number a finding keeps on its card from the round it was found in onwards
			$table->unsignedInteger('number');
			$table->string('location')->nullable();
			$table->text('text');
			$table->string('status')->default('open');
			$table->text('reason')->nullable();
			$table->foreignId('first_review_round_id')->constrained('review_rounds')->cascadeOnDelete();
			$table->foreignId('last_review_round_id')->constrained('review_rounds')->cascadeOnDelete();
			$table->foreignId('resolved_review_round_id')->nullable()->constrained('review_rounds')->nullOnDelete();
			$table->timestamps();

			$table->unique(['card', 'number']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('review_findings');
	}
};
