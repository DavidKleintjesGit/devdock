<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('review_comments', function (Blueprint $table): void {
			$table->unsignedBigInteger('parent_comment_id')->nullable()->after('comment_id');
		});
	}

	public function down(): void
	{
		Schema::table('review_comments', function (Blueprint $table): void {
			$table->dropColumn('parent_comment_id');
		});
	}
};
