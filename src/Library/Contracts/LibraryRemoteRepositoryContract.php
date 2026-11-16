<?php

declare(strict_types=1);

namespace Src\Library\Contracts;

use RuntimeException;
use Src\Library\Enums\LibraryItemKindEnum;

interface LibraryRemoteRepositoryContract
{
	/**
	 * Whether this is the address of a folder on Bitbucket or GitHub, such as
	 * https://bitbucket.org/team/repo/src/main/ai/instructions.
	 */
	public function isAddress(string $location): bool;

	/**
	 * The rules (name => text) or the skills (name => packed files) in that
	 * folder of the repository, as they stand on its branch now.
	 *
	 * @return array<string, string>
	 *
	 * @throws RuntimeException when the repository cannot be read
	 */
	public function readFolder(string $address, LibraryItemKindEnum $kind): array;
}
