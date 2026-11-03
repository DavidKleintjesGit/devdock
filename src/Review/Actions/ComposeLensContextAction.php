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
		public readonly array $rules = [],
	) {}

	public function handle(): string
	{
		return implode("\n", array_filter([
			__('Je bent :name, een reviewer op deze werkkopie.', ['name' => $this->lensEntity->name]),
			$this->lensEntity->description,
			'',
			GetLensBriefAction::execute($this->lensEntity->name)?->text,
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
			__('Dit vond je in de vorige ronde, en de agent heeft er sindsdien aan gewerkt:'),
			'',
			$this->previousReport,
			'',
			__('Kijk eerst of deze bevindingen zijn opgelost, en zoek pas daarna naar nieuwe. Neem alleen mee wat toen OPEN of nieuw was; wat al RESOLVED stond laat je weg.'),
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

		if ($this->rules !== []) {
			$lines[] = __('- Beoordeel tegen deze regelbestanden: :files', ['files' => implode(', ', $this->rules)]);
		}

		if ($this->lensEntity->command !== null) {
			$lines[] = __('- Draai :command en neem de uitkomst mee in je oordeel.', [
				'command' => $this->lensEntity->command,
			]);
		}

		return implode("\n", [
			...$lines,
			__('- Wil je weten waarom code er zo uitziet, kijk dan in het Jira-ticket, in eerdere pull requests in Bitbucket (zoek de operatie met discover, roep hem aan met executeRead) of in git log en git blame. Je oordeel gaat over deze wijziging.'),
			__('- Verander geen bestand en schrijf niets in git. Je levert alleen een oordeel.'),
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
			...$this->earlierFindingsFormat(),
		]);
	}

	/**
	 * How a lens with a previous round writes down what became of its earlier
	 * findings. RESOLVED and OPEN are read by ReadVerdictAction and stay as
	 * written here.
	 *
	 * @return array<int, string>
	 */
	private function earlierFindingsFormat(): array
	{
		if (blank($this->previousReport)) {
			return [];
		}

		return [
			'',
			__('Omdat je een vorige ronde hebt, begint je blok met wat er van je eerdere bevindingen werd, één regel per bevinding. Daaronder volgen de nieuwe, genummerd. PASS alleen als niets meer OPEN staat en je niets nieuws vond:'),
			'',
			'```verdict',
			'FAIL',
			'',
			__('Eerder gevonden:'),
			__('- RESOLVED <bestand>:<regel> <wat er was, in een paar woorden>'),
			__('- OPEN <bestand>:<regel> <wat er nog mis is>'),
			'',
			__('Nieuw:'),
			'1. <bestand>:<regel>',
			'   <wat er mis is, in een of twee zinnen>',
			'```',
		];
	}
}
