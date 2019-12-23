<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Entity\Embeddable;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IEntityAwareProperty;
use Nextras\Orm\Entity\ImmutableDataTrait;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\LogicException;
use Nextras\Orm\NotSupportedException;


abstract class Embeddable implements IEmbeddable
{
	use ImmutableDataTrait;

	/** @var IEntity|null */
	protected $parentEntity;


	protected function __construct(?array $data = null)
	{
		$this->metadata = $this->createMetadata();
		if ($data !== null) {
			$this->setImmutableData($data);
		}
	}


	public function setRawValue(array $data)
	{
		$this->metadata = $this->createMetadata();
		foreach ($this->metadata->getProperties() as $name => $propertyMetadata) {
			if ($propertyMetadata->isVirtual) continue;
			$this->data[$name] = $data[$name] ?? null;
		}
	}


	public function getRawValue(): array
	{
		$out = [];

		foreach ($this->metadata->getProperties() as $name => $propertyMetadata) {
			if ($propertyMetadata->isVirtual) continue;

			if ($propertyMetadata->wrapper === null) {
				if (!isset($this->validated[$name])) {
					$this->initProperty($propertyMetadata, $name);
				}

				$out[$name] = $this->data[$name];

			} else {
				$wrapper = $this->data[$name];
				\assert($wrapper instanceof IProperty);
				$out[$name] = $wrapper->getRawValue();
			}
		}

		return $out;
	}


	public function onAttach(IEntity $entity)
	{
		$this->parentEntity = $entity;
	}


	public function &__get($name)
	{
		return $this->getValue($name);
	}


	public function __isset($name)
	{
		if (!$this->metadata->hasProperty($name)) {
			return false;
		}
		return $this->hasValue($name);
	}


	public function __set($name, $value)
	{
		throw new NotSupportedException("Embeddable object is immutable.");
	}


	public function __unset($name)
	{
		throw new NotSupportedException("Embeddable object is immutable.");
	}


	protected function initProperty(PropertyMetadata $metadata, string $name, bool $initValue = true)
	{
		$this->validated[$name] = true;

		if ($metadata->wrapper !== null) {
			$wrapper = $this->createPropertyWrapper($metadata);
			if ($initValue) {
				$wrapper->setRawValue($this->data[$metadata->name] ?? null);
			}
			$this->data[$name] = $wrapper;
			return;
		}

		// embeddable does not support property default value by design
		$this->data[$name] = $this->data[$name] ?? null;
	}


	private function createPropertyWrapper(PropertyMetadata $metadata): IProperty
	{
		$class = $metadata->wrapper;
		$wrapper = new $class($metadata);
		\assert($wrapper instanceof IProperty);

		if ($wrapper instanceof IEntityAwareProperty) {
			if ($this->parentEntity === null) {
				throw new InvalidStateException("");
			} else {
				$wrapper->setPropertyEntity($this->parentEntity);
			}
		}

		return $wrapper;
	}


	private function setImmutableData(array $data)
	{
		if (\count($data) !== \count($this->metadata->getProperties())) {
			$n = \count($data);
			$total = \count($this->metadata->getProperties());
			$class = \get_class($this);
			throw new InvalidArgumentException("Only $n of $total values were set. Construct $class embeddable with all its properties. ");
		}

		foreach ($data as $name => $value) {
			$metadata = $this->metadata->getProperty($name);
			if (!isset($this->validated[$name])) {
				$this->initProperty($metadata, $name, false);
			}

			$property = $this->data[$name];
			if ($property instanceof IPropertyContainer) {
				$property->setInjectedValue($value);
				continue;
			} elseif ($property instanceof IProperty) {
				$class = \get_class($this);
				throw new LogicException("You cannot set property wrapper's value on $class::\$$name directly.");
			}

			if ($metadata->hasSetter) {
				$cb = [$this, $metadata->hasSetter];
				\assert(is_callable($cb));
				$value = \call_user_func($cb, $value, $metadata);
			}

			$this->validate($metadata, $name, $value);
			$this->data[$name] = $value;
		}
	}
}
