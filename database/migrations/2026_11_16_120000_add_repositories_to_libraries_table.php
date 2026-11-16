<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('libraries', function (Blueprint $table): void {
			$table->string('repository')->nullable()->after('skills_folder');
			$table->string('skills_repository')->nullable()->after('repository');
		});
	}

	public function down(): void
	{
		Schema::table('libraries', function (Blueprint $table): void {
			$table->dropColumn(['repository', 'skills_repository']);
		});
	}
};
