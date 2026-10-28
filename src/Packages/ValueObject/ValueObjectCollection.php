<?php

declare(strict_types=1);

namespace Src\Packages\ValueObject;

use Countable;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
use Illuminate\Support\Collection;

/**
 * @mixin Collection
 */
abstract class ValueObjectCollection implements JsonSerializable, IteratorAggregate, Countable
{
	private array $valueObjects;

	/**
	 * List of methods that should be directly forwarded to Collection.
	 * These methods accept callable, so it makes sense to just directly forward the call to Collection.
	 */
	private array $directCalls = [
		'chunkWhile', 'filter', 'first', 'last', 'map', 'each', 'mapToDictionary', 'mapWithKeys', 'toArray', 'transform', 'get',
	];

	abstract protected function __construct();

	public static function fromArray(array $array): static
	{
		$collection = new static(...$array);
		$collection->loadArray($array);

		return $collection;
	}

	/** Return an array of arrays instead of value objects. */
	public function toPlainArray(): array
	{
		return $this->map(function (ValueObject $value) {
			return $value->toPlainArray();
		})->toArray();
	}

	/** Convert the collection of value objects to a view-friendly array. */
	public function toViewArray(bool $toPlainArray = false): array
	{
		return $this->map(function (ValueObject $value) use ($toPlainArray) {
			return $value->toViewArray($toPlainArray);
		})->toArray();
	}

	public function jsonSerialize(): array
	{
		return $this->toPlainArray();
	}

	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->valueObjects);
	}

	public function count(): int
	{
		return count($this->valueObjects);
	}

	/** Get the value objects in the value object collection that are not present in the given value object collection. */
	public function recursiveDiff(ValueObjectCollection $otherItems): ValueObjectCollection
	{
		/** @var ValueObjectCollection $className */
		$className = $otherItems::class;

		$data = $this->filter(function (ValueObject $item) use ($otherItems) {
			return $otherItems->filter(function (ValueObject $other) use ($item) {
				return $item->isEqual($other);
			})->isEmpty();
		})->toArray();

		return $className::fromArray($data);
	}

	public function isEqual(ValueObjectCollection $collection, bool $strict = true): bool
	{
		if ($strict && static::class !== get_class($collection)) {
			return false;
		}

		if ($this->count() !== $collection->count()) {
			return false;
		}

		$equal = true;

		$this->each(function (ValueObject $original, int $key) use ($collection, &$equal, $strict) {
			$compare = $collection->get($key);

			if ( ! $original->isEqual($compare, $strict)) {
				$equal = false;

				return false;
			}

			return true;
		});

		return $equal;
	}

	public function push(ValueObject $item): static
	{
		$this->valueObjects[] = $item;

		return $this;
	}

	private function loadArray(array $array): void
	{
		$this->valueObjects = $array;
	}

	public function __call(string $name, array $arguments): mixed
	{
		$collection = collect($this->valueObjects);

		if (in_array($name, $this->directCalls, true)) {
			return $collection->{$name}(...$arguments);
		}

		// we map the ValueObject into its plain array, so we could use Collection's
		// methods such as sum(), pluck(), etc.
		return $collection->map(function (ValueObject $value) {
			return $value->toArray();
		})->{$name}(...$arguments);
	}
}
