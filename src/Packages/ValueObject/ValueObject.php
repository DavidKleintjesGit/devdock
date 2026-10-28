<?php

declare(strict_types=1);

namespace Src\Packages\ValueObject;

use Exception;
use JsonSerializable;
use BadMethodCallException;
use InvalidArgumentException;
use Src\Packages\ValueObject\Exceptions\InvalidIdException;
use Src\Packages\ValueObject\Contracts\StringableValueObject;
use Src\Packages\ValueObject\Exceptions\NonStringableValueObjectException;

abstract class ValueObject implements JsonSerializable
{
	public function __construct()
	{
		$this->validateIdOrUuid();
	}

	/**
	 * Validate that either id or uuid is set if the class name contains "Id".
	 *
	 * @throws InvalidIdException
	 */
	protected function validateIdOrUuid(): void
	{
		if (strpos(static::class, 'Id') !== false) {
			if ((property_exists($this, 'id') && is_null($this->id)) &&
				(property_exists($this, 'uuid') && is_null($this->uuid))) {
				throw new InvalidIdException();
			}
		}
	}

	/**
	 * Initialize value object from array
	 * @param array $values
	 * @return static
	 */
	public static function fromArray(array $values): static
	{
		$properties = get_class_vars(static::class);
		$missing = array_diff_key($properties, $values);

		if ($missing) {
			throw new InvalidArgumentException('Required properties ' . implode(', ', array_keys($missing)) . ' are missing.');
		}

		$tooMany = array_diff_key($values, $properties);
		if ($tooMany) {
			throw new InvalidArgumentException('Undefined properties ' . implode(', ', array_keys($tooMany)) . ' found.');
		}

		$arguments = [];

		foreach ($properties as $property => $value) {
			$arguments[] = $values[$property];
		}

		return new static(...$arguments);
	}

	/**
	 * Initialize value object from string if value object has single property
	 * @throws Exception
	 */
	public static function fromString(string $string): static
	{
		$properties = get_class_vars(static::class);
		if (count($properties) !== 1) {
			throw new BadMethodCallException('More than one property defined, use the constructor or fromArray instead');
		}

		return new static($string);
	}

	public function isEqual(ValueObject $object, bool $strict = true): bool
	{
		if ($strict && static::class !== get_class($object)) {
			return false;
		}

		$isEqual = false;
		$properties = get_class_vars(static::class);

		foreach ($properties as $name => $defaultValue) {
			$value = $this->{$name};
			if (is_object($value)) {
				if ($value instanceof self) {
					$isEqual = $value->isEqual($object->{$name});
				} else {
					// it's a standard class object, so we use non-strict comparison.
					$isEqual = $value == $object->{$name};
				}
			} else {
				// it's a primitive types, so we do strict comparison.
				$isEqual = $value === $object->{$name};
			}

			if (is_float($value)) {
				$isEqual = (string) $value === (string) $object->{$name};
			}

			if ( ! $isEqual) {
				return false;
			}
		}

		return $isEqual;
	}

	public function toArray(bool $toPlainArray = false): array
	{
		$properties = get_class_vars(static::class);

		foreach ($properties as $name => $value) {
			if ($this->{$name} instanceof self) {
				$childProperties = get_class_vars($this->{$name}::class);

				if (count($childProperties) === 1) {
					$properties[$name] = $this->{$name}->{array_key_first($childProperties)};
				} elseif ($toPlainArray && count($childProperties) > 1) {
					$properties[$name] = $this->{$name}->toPlainArray();
				} else {
					$properties[$name] = $this->{$name}->toArray();
				}
			} else {
				$properties[$name] = $this->{$name};
			}
		}

		return $properties;
	}

	public function toViewArray(bool $toPlainArray = false): array
	{
		$properties = get_class_vars(static::class);
		$viewArray = [];

		foreach ($properties as $name => $value) {
			$propertyValue = $this->{$name};

			// Convert property name to snake_case
			$snakeCaseName = $this->camelToSnake($name);

			if ($propertyValue instanceof self) {
				$childClass = get_class($propertyValue);

				// Check if the class name contains "Id"
				if (strpos($childClass, 'Id') !== false) {
					$viewArray[$snakeCaseName] = $propertyValue->uuid;
				} else {
					$viewArray[$snakeCaseName] = $propertyValue->toViewArray($toPlainArray);
				}
			} elseif (is_array($propertyValue)) {
				$viewArray[$snakeCaseName] = $this->processArrayForView($propertyValue, $toPlainArray);
			} else {
				$viewArray[$snakeCaseName] = $propertyValue;
			}
		}

		return $viewArray;
	}

	/** Helper method to convert camelCase to snake_case. */
	private function camelToSnake(string $input): string
	{
		return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
	}

	/** Helper method to process arrays and convert keys to snake_case. */
	private function processArrayForView(array $array, bool $toPlainArray = false): array
	{
		$processedArray = [];

		foreach ($array as $key => $value) {
			if (is_string($key)) {
				$key = $this->camelToSnake($key);
			}

			if ($value instanceof self) {
				$childClass = get_class($value);

				if (strpos($childClass, 'Id') !== false) {
					$processedArray[$key] = $value->id;
				} else {
					$processedArray[$key] = $value->toViewArray($toPlainArray);
				}
			} elseif (is_array($value)) {
				$processedArray[$key] = $this->processArrayForView($value, $toPlainArray);
			} else {
				$processedArray[$key] = $value;
			}
		}

		return $processedArray;
	}

	public function toPlainArray(): array
	{
		return $this->toArray(true);
	}

	public function jsonSerialize(): array
	{
		return $this->toPlainArray();
	}

	protected function validate(): void
	{
	}

	/** @throws NonStringableValueObjectException */
	public function __toString(): string
	{
		if ($this->isStringableValueObject()) {
			return $this->toString();
		}

		$properties = get_class_vars(static::class);

		if (count($properties) === 1) {
			return (string) $this->{array_keys($properties)[0]};
		}

		throw new NonStringableValueObjectException('Value Object with multiple properties should implement StringableValueObject to be stringable');
	}

	private function isStringableValueObject(): bool
	{
		return in_array(StringableValueObject::class, class_implements($this), true);
	}
}
