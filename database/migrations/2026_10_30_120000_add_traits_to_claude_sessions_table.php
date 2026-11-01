<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('claude_sessions', function (Blueprint $table): void {
			// the folder the session ran in, which tells a working copy from the project
			$table->string('cwd')->nullable()->after('folder');
			$table->timestamp('first_active_at')->nullable()->after('last_active_at');
			$table->unsignedInteger('active_days')->default(1)->after('first_active_at');
			$table->boolean('has_commit')->default(false)->after('active_days');
			$table->boolean('is_interrupted')->default(false)->after('has_commit');
			$table->boolean('is_unanswered')->default(false)->after('is_interrupted');
			$table->boolean('is_pinned')->default(false)->after('is_unanswered');
		});
	}

	public function down(): void
	{
		Schema::table('claude_sessions', function (Blueprint $table): void {
			$table->dropColumn(['cwd', 'first_active_at', 'active_days', 'has_commit', 'is_interrupted', 'is_unanswered', 'is_pinned']);
		});
	}
};
