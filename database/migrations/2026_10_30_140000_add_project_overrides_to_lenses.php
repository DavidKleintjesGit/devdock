<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('lenses', function (Blueprint $table): void {
			// the rule files a lens reads, as patterns that match in any project
			$table->json('rules')->nullable()->after('paths');

			// a lens of one project only; empty means the standard
			$table->string('project')->nullable()->after('position');

			$table->dropUnique(['name']);
			$table->unique(['name', 'project']);
		});

		Schema::table('lens_settings', function (Blueprint $table): void {
			$table->json('paths')->nullable()->after('is_enabled');
			$table->json('rules')->nullable()->after('paths');

			// an empty string switches the command off in this project
			$table->string('command')->nullable()->after('rules');
		});

		Schema::create('project_workflows', function (Blueprint $table): void {
			$table->id();
			$table->string('project')->unique();
			$table->unsignedInteger('max_rounds')->nullable();
			$table->string('after_rounds')->nullable();

			// comes after the way of working of the standard, never instead of it
			$table->text('context')->nullable();
			$table->timestamps();
		});

		$standard = [
			'reviewer-code' => ['code', 'Huisregels, namen, logica die in een view zit.', ['app/**', 'src/**', 'resources/views/**'], ['**/coding_standards.md', '**/code.md'], null, 2],
			'reviewer-test' => ['tests', 'Draait de suite en kijkt wat er gedekt is.', ['app/**', 'src/**', 'tests/**'], ['**/testing.md', '**/tests.md'], 'php artisan test', 3],
			'reviewer-security' => ['beveiliging', 'Autorisatie, invoer die niemand controleert, geheimen in de code.', ['app/**', 'src/**', 'routes/**'], ['**/security.md'], null, 4],
			'reviewer-ui' => ['ux', 'Het scherm zoals een gebruiker het tegenkomt.', ['resources/views/**', 'resources/css/**'], ['**/DESIGN-SYSTEM.md'], null, 5],
		];

		foreach ($standard as $old => [$name, $description, $paths, $rules, $command, $position]) {
			DB::table('lenses')->where('name', $old)->update([
				'name' => $name,
				'description' => $description,
				'paths' => json_encode($paths),
				'rules' => json_encode($rules),
				'command' => $command,
				'position' => $position,
			]);
		}

		if (DB::table('lenses')->where('name', 'architectuur')->doesntExist()) {
			DB::table('lenses')->insert([
				'name' => 'architectuur',
				'description' => 'Laagindeling, en klassen die te veel doen.',
				'paths' => json_encode(['app/**', 'src/**']),
				'rules' => json_encode(['**/architecture.md']),
				'command' => null,
				'position' => 1,
				'created_at' => now(),
				'updated_at' => now(),
			]);
		}

		DB::table('lenses')->whereIn('name', ['reviewer-db', 'reviewer-docs'])->update(['position' => 9]);
	}

	public function down(): void
	{
		Schema::dropIfExists('project_workflows');

		Schema::table('lens_settings', function (Blueprint $table): void {
			$table->dropColumn(['paths', 'rules', 'command']);
		});

		Schema::table('lenses', function (Blueprint $table): void {
			$table->dropUnique(['name', 'project']);
			$table->unique(['name']);
			$table->dropColumn(['rules', 'project']);
		});
	}
};
