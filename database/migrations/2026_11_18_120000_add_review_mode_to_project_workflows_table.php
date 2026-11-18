<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('project_workflows', function (Blueprint $table): void {
			$table->string('review_mode')->nullable()->after('after_rounds');
		});
	}

	public function down(): void
	{
		Schema::table('project_workflows', function (Blueprint $table): void {
			$table->dropColumn('review_mode');
		});
	}
};
