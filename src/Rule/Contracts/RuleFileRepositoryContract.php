<?php

declare(strict_types=1);

namespace Src\Rule\Contracts;

use Src\Rule\Objects\RuleFileEntity;
use Src\Rule\Objects\RuleFileEntityCollection;
use Src\Rule\Objects\RuleFileId;
use Src\Rule\Objects\RuleFileObject;
use Src\Rule\Objects\RuleFileObjectCollection;

interface RuleFileRepositoryContract
{
	public function create(RuleFileObject $ruleFileObject): RuleFileEntity;

	public function getAll(): RuleFileEntityCollection;

	public function getById(RuleFileId $ruleFileId): ?RuleFileEntity;

	/**
	 * Brings the stored rule files in line with what the scan found.
	 */
	public function replaceAll(RuleFileObjectCollection $ruleFileObjects): RuleFileEntityCollection;
}
