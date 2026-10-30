<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('review_results', function (Blueprint $table): void {
			$table->string('activity')->nullable()->after('verdict');
			$table->unsignedInteger('tool_calls')->default(0)->after('activity');
			$table->decimal('cost', 10, 4)->nullable()->after('tool_calls');
			$table->timestamp('last_seen_at')->nullable()->after('started_at');
		});
	}

	public function down(): void
	{
		Schema::table('review_results', function (Blueprint $table): void {
			$table->dropColumn(['activity', 'tool_calls', 'cost', 'last_seen_at']);
		});
	}
};
