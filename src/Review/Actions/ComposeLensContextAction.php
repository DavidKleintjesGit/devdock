<?php

declare(strict_types=1);

namespace Src\Review\Actions;

use Src\Packages\Cqrs\Command;
use Src\Review\Objects\LensEntity;

/**
 * The context one lens is given, word for word as it reads it from its own
 * file. A lens gets no history from the agent that built the work: it judges
 * what it sees, so it is not talked round by the reasoning that produced it.
 *
 * The verdict block is a machine format and stays as written here; its other
 * half is ReadVerdictAction.
 *
 * @method static string execute(LensEntity $lensEntity, array $files, ?string $previousReport)
 */
class ComposeLensContextAction extends Command
{
	/**
	 * @param  array<int, string>  $files
	 */
	public function __construct(
		public readonly LensEntity $lensEntity,
		public readonly array $files,
		public readonly ?string $previousReport = null,
	) {}

	public function handle(): string
	{
		return implode("\n", array_filter([
			__('Je bent :name, een reviewer op deze werkkopie.', ['name' => $this->lensEntity->name]),
			$this->lensEntity->description,
			'',
			$this->whatChanged(),
			$this->previousRound(),
			$this->howToGoAbout(),
		]));
	}

	private function whatChanged(): string
	{
		$lines = [__('De wijziging die je beoordeelt raakt deze bestanden:')];

		foreach ($this->files as $file) {
			$lines[] = '- ' . $file;
		}

		return implode("\n", $lines) . "\n";
	}

	private function previousRound(): ?string
	{
		if (blank($this->previousReport)) {
			return null;
		}

		return implode("\n", [
			__('Je vorige ronde liet dit open. Loop het per punt na en zeg of het is opgelost:'),
			'',
			$this->previousReport,
			'',
		]);
	}

	private function howToGoAbout(): string
	{
		$lines = [
			__('Werkwijze:'),
			__('- Lees de bestanden die je noemt echt, en noem bestand en regelnummer.'),
			__('- CLAUDE.md en CLAUDE.local.md staan in deze map; die lees je zelf en ze gaan voor je eigen smaak.'),
		];

		if ($this->lensEntity->command !== null) {
			$lines[] = __('- Draai :command en neem de uitkomst mee in je oordeel.', [
				'command' => $this->lensEntity->command,
			]);
		}

		return implode("\n", [
			...$lines,
			__('- Verander geen bestand, commit niet en push niet. Je levert alleen een oordeel.'),
			__('- Noem alleen wat echt fout is. Smaak is geen bevinding.'),
			'',
			__('Antwoord met precies dit blok en niets erbuiten. PASS als je niets vond:'),
			'',
			'```verdict',
			'PASS',
			'```',
			'',
			__('Of, met bevindingen:'),
			'',
			'```verdict',
			'FAIL',
			'',
			'1. <bestand>:<regel>',
			'   <wat er mis is, in een of twee zinnen>',
			'```',
		]);
	}
}
