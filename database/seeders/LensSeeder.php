<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Review\Models\Lens;

class LensSeeder extends Seeder
{
	public function run(): void
	{
		$lenses = [
			[
				'name' => 'reviewer-code',
				'description' => 'Leest de wijziging tegen de regels uit CLAUDE.md: naamgeving, lagen, waar logica hoort.',
				'paths' => ['app/*', 'src/*', '*.php'],
				'command' => null,
				'position' => 1,
			],
			[
				'name' => 'reviewer-test',
				'description' => 'Draait de tests van wat is aangeraakt en kijkt of er dekking ontbreekt op de nieuwe paden.',
				'paths' => ['tests/*', 'app/*', 'src/*'],
				'command' => 'php artisan test',
				'position' => 2,
			],
			[
				'name' => 'reviewer-ui',
				'description' => 'Kijkt of bestaande componenten zijn hergebruikt en of de template alleen rendert.',
				'paths' => ['resources/views/*', 'resources/css/*', 'resources/js/*'],
				'command' => null,
				'position' => 3,
			],
			[
				'name' => 'reviewer-db',
				'description' => 'Migraties, indexen en queries: wat er N+1 wordt en wat er bij een rollback misgaat.',
				'paths' => ['database/*', 'app/Models/*', 'src/*/Models/*'],
				'command' => null,
				'position' => 4,
			],
			[
				'name' => 'reviewer-security',
				'description' => 'Autorisatie, invoer en wat er naar buiten gaat.',
				'paths' => ['app/Http/*', 'routes/*', 'config/*'],
				'command' => null,
				'position' => 5,
			],
			[
				'name' => 'reviewer-docs',
				'description' => 'Controleert of een wijziging in gedrag ook in de documentatie landt.',
				'paths' => ['*.md', 'docs/*'],
				'command' => null,
				'position' => 6,
			],
		];

		foreach ($lenses as $lens) {
			Lens::query()->updateOrCreate(['name' => $lens['name']], $lens);
		}
	}
}
