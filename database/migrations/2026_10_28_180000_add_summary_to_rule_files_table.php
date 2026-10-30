<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('rule_files', function (Blueprint $table): void {
			// what a file says of itself in one line: a memory writes it in its
			// frontmatter, so the page can show it without opening the file
			$table->string('summary')->nullable()->after('path');
		});
	}

	public function down(): void
	{
		Schema::table('rule_files', function (Blueprint $table): void {
			$table->dropColumn('summary');
		});
	}
};
