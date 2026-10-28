<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('pull_requests', function (Blueprint $table) {
			$table->id();
			$table->string('repository');
			$table->unsignedInteger('number');
			$table->string('title');
			$table->string('state');
			$table->boolean('draft')->default(false);
			$table->string('author')->nullable();
			$table->string('source_branch')->nullable();
			$table->string('destination_branch')->nullable();
			$table->unsignedTinyInteger('approvals')->default(0);
			$table->unsignedTinyInteger('reviewers')->default(0);
			$table->string('changes_requested_by')->nullable();
			$table->unsignedSmallInteger('comment_count')->default(0);
			$table->string('url')->nullable();
			$table->timestamp('changed_at')->nullable();
			$table->timestamps();

			$table->unique(['repository', 'number']);
			$table->index('state');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('pull_requests');
	}
};
