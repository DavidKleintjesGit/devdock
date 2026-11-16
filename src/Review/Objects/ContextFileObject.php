<?php

declare(strict_types=1);

namespace Src\Review\Objects;

use Src\Packages\ValueObject\ValueObject;

/**
 * One piece of what an agent or a lens is given: a file, or a text DevDock
 * puts together, with where it comes from.
 */
class ContextFileObject extends ValueObject
{
	public function __construct(
		public readonly string $key,
		public readonly string $name,
		public readonly ?string $group = null,
		public readonly ?string $description = null,
		public readonly ?string $path = null,
		public readonly ?string $contents = null,
		// the card tab where this is read in full, when it is not a text of its own
		public readonly ?string $tab = null,
	) {
		parent::__construct();
	}

	/**
	 * The route of the page where this piece is set, or null when no page sets it.
	 */
	public function settingsRoute(): ?string
	{
		$key = $this->baseKey();

		return match (true) {
			str_starts_with($key, 'global:'), str_starts_with($key, 'checkout:'), str_starts_with($key, 'project:') => 'rules',
			str_starts_with($key, 'skill:') => 'skills',
			str_starts_with($key, 'step:'), in_array($key, ['standing', 'task', 'project', 'definition', 'assignment'], true) => 'workflows',
			default => null,
		};
	}

	/**
	 * Where this piece is kept and how it comes about, so you know what to change to change it.
	 */
	public function origin(): string
	{
		$key = $this->baseKey();

		return match (true) {
			str_starts_with($key, 'global:') => __('Een bestand in jouw map ~/.claude. Het geldt voor elk project op deze computer.'),
			str_starts_with($key, 'checkout:') => __('Een bestand in de checkout van het project. Claude leest het ook in de werkkopie, omdat die erbinnen staat.'),
			str_starts_with($key, 'project:') => __('Een bestand in de werkkopie, uit de repository van het project.'),
			str_starts_with($key, 'skill:') => __('Een skill op deze computer. Claude krijgt de omschrijving mee en leest de rest pas als hij de skill gebruikt.'),
			str_starts_with($key, 'step:'), $key === 'standing' => __('Een blok uit Workflows, dat DevDock in het taakbestand zet.'),
			$key === 'memory' => __('Het geheugen dat Claude Code voor dit project bijhoudt, in jouw map ~/.claude.'),
			$key === 'task' => __('Door DevDock opgesteld uit de taak, het ticket en de blokken in Workflows, en in de werkkopie gezet als .devdock/agent.md.'),
			$key === 'work', $key === 'chat' => __('Vast in DevDock zelf, voor elke taak gelijk. Het staat in de code, niet in een instelling.'),
			$key === 'last-turn' => __('De opdracht die bij de laatste beurt werd verstuurd.'),
			$key === 'conversation' => __('De sessie van Claude Code, die elke beurt wordt hervat.'),
			$key === 'assignment' => __('Door DevDock opgesteld uit de lens in Workflows en de bestanden die deze ronde raakt, en in de werkkopie gezet onder .devdock/lens.'),
			$key === 'report' => __('Wat deze lens in de vorige ronde vond; hij krijgt het mee om te zien wat er sindsdien veranderde.'),
			$key === 'instruction' => __('Vast in DevDock zelf: de ene regel die de lens naar zijn opdracht wijst.'),
			$key === 'project', $key === 'definition' => __('Ingesteld in Workflows.'),
			default => __('Door DevDock meegegeven.'),
		};
	}

	/**
	 * The key without the lens it belongs to, when sources of several lenses stand side by side.
	 */
	private function baseKey(): string
	{
		return preg_match('/^lens:[^:]+:(.+)$/', $this->key, $found) === 1 ? $found[1] : $this->key;
	}

	/**
	 * The button text that leads to the page where this piece is set.
	 */
	public function settingsLabel(): ?string
	{
		return match ($this->settingsRoute()) {
			'rules' => __('Instellen in Regels'),
			'skills' => __('Instellen in Skills'),
			'workflows' => __('Instellen in Workflows'),
			default => null,
		};
	}
}
