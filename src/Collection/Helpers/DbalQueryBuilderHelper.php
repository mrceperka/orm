<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Helpers;

use Nette\Utils\Arrays;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Functions\IQueryBuilderFunction;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\Embeddable\EmbeddableContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\Reflection\PropertyRelationshipMetadata as Relationship;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\DbalMapper;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\NotSupportedException;
use Nextras\Orm\Repository\IRepository;


/**
 * QueryBuilder helper for Nextras Dbal.
 */
class DbalQueryBuilderHelper
{
	/** @var IModel */
	private $model;

	/** @var IRepository */
	private $repository;

	/** @var DbalMapper */
	private $mapper;

	/** @var string */
	private $platformName;


	public static function getAlias(string $name): string
	{
		static $counter = 1;
		if (preg_match('#^([a-z0-9_]+\.){0,2}+([a-z0-9_]+?)$#i', $name, $m)) {
			return $m[2];
		}

		return '_join' . $counter++;
	}


	public function __construct(IModel $model, IRepository $repository, DbalMapper $mapper)
	{
		$this->model = $model;
		$this->repository = $repository;
		$this->mapper = $mapper;
		$this->platformName = $mapper->getDatabasePlatform()->getName();
	}


	/**
	 * @param string|array<mixed> $expr
	 */
	public function processPropertyExpr(QueryBuilder $builder, $expr): DbalExpressionResult
	{
		if (\is_array($expr)) {
			$function = \array_shift($expr);
			$collectionFunction = $this->repository->getCollectionFunction($function);
			if (!$collectionFunction instanceof IQueryBuilderFunction) {
				throw new InvalidArgumentException("Collection function $function has to implement " . IQueryBuilderFunction::class . ' interface.');
			}
			return $collectionFunction->processQueryBuilderExpression($this, $builder, $expr);
		}

		[$tokens, $sourceEntity] = ConditionParserHelper::parsePropertyExpr($expr);
		return $this->processTokens($tokens, $sourceEntity, $builder);
	}


	/**
	 * @param array<mixed> $expr
	 */
	public function processFilterFunction(QueryBuilder $builder, array $expr): DbalExpressionResult
	{
		$function = isset($expr[0]) ? \array_shift($expr) : ICollection::AND;
		$collectionFunction = $this->repository->getCollectionFunction($function);
		if (!$collectionFunction instanceof IQueryBuilderFunction) {
			throw new InvalidArgumentException("Collection function $function has to implement " . IQueryBuilderFunction::class . ' interface.');
		}
		return $collectionFunction->processQueryBuilderExpression($this, $builder, $expr);
	}


	/**
	 * @param string|array<mixed> $expr
	 * @return array<mixed>
	 */
	public function processOrder(QueryBuilder $builder, $expr, string $direction): array
	{
		$columnReference = $this->processPropertyExpr($builder, $expr);
		return $this->processOrderDirection($columnReference, $direction);
	}


	/**
	 * @return array<mixed>
	 */
	private function processOrderDirection(DbalExpressionResult $expression, string $direction): array
	{
		if ($this->platformName === 'mysql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC', $expression->args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC', $expression->args];
			} elseif ($direction === ICollection::ASC_NULLS_LAST) {
				return ['%ex IS NULL, %ex ASC', $expression->args, $expression->args];
			} elseif ($direction === ICollection::DESC_NULLS_FIRST) {
				return ['%ex IS NOT NULL, %ex DESC', $expression->args, $expression->args];
			}
		} elseif ($this->platformName === 'mssql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC', $expression->args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC', $expression->args];
			} elseif ($direction === ICollection::ASC_NULLS_LAST) {
				return ['CASE WHEN %ex IS NULL THEN 1 ELSE 0 END, %ex ASC', $expression->args, $expression->args];
			} elseif ($direction === ICollection::DESC_NULLS_FIRST) {
				return ['CASE WHEN %ex IS NOT NULL THEN 1 ELSE 0 END, %ex DESC', $expression->args, $expression->args];
			}
		} elseif ($this->platformName === 'pgsql') {
			if ($direction === ICollection::ASC || $direction === ICollection::ASC_NULLS_LAST) {
				return ['%ex ASC', $expression->args];
			} elseif ($direction === ICollection::DESC || $direction === ICollection::DESC_NULLS_FIRST) {
				return ['%ex DESC', $expression->args];
			} elseif ($direction === ICollection::ASC_NULLS_FIRST) {
				return ['%ex ASC NULLS FIRST', $expression->args];
			} elseif ($direction === ICollection::DESC_NULLS_LAST) {
				return ['%ex DESC NULLS LAST', $expression->args];
			}
		}

		throw new NotSupportedException();
	}


	/**
	 * @param mixed $value
	 * @return mixed
	 */
	public function normalizeValue($value, PropertyMetadata $propertyMetadata, IConventions $conventions)
	{
		if (isset($propertyMetadata->types['array'])) {
			if (\is_array($value) && !\is_array(reset($value))) {
				$value = [$value];
			}
			if ($propertyMetadata->isPrimary) {
				foreach ($value as $subValue) {
					if (!Arrays::isList($subValue)) {
						throw new InvalidArgumentException('Composite primary value has to be passed as a list, without array keys.');
					}
				}
			}
		}

		if ($propertyMetadata->wrapper !== null) {
			$property = $propertyMetadata->getWrapperPrototype();
			if (\is_array($value)) {
				$value = \array_map(function ($subValue) use ($property) {
					return $property->convertToRawValue($subValue);
				}, $value);
			} else {
				$value = $property->convertToRawValue($value);
			}
		}

		$tmp = $conventions->convertEntityToStorage([$propertyMetadata->name => $value]);
		$value = \reset($tmp);

		return $value;
	}


	/**
	 * @param array<string> $tokens
	 * @param class-string<\Nextras\Orm\Entity\IEntity>|null $sourceEntity
	 */
	private function processTokens(array $tokens, ?string $sourceEntity, QueryBuilder $builder): DbalExpressionResult
	{
		$lastToken = \array_pop($tokens);
		\assert($lastToken !== null);

		$currentMapper = $this->mapper;
		$currentAlias = $builder->getFromAlias();
		$currentReflection = $currentMapper->getConventions();
		$currentEntityMetadata = $currentMapper->getRepository()->getEntityMetadata($sourceEntity);
		$propertyPrefixTokens = "";
		$makeDistinct = false;

		foreach ($tokens as $tokenIndex => $token) {
			$property = $currentEntityMetadata->getProperty($token);
			if ($property->relationship !== null) {
				[
					$currentAlias,
					$currentReflection,
					$currentEntityMetadata,
					$currentMapper
				] = $this->processRelationship(
					$tokens,
					$builder,
					$property,
					$currentReflection,
					$currentMapper,
					$currentAlias,
					$token,
					$tokenIndex,
					$makeDistinct
				);
			} elseif ($property->wrapper === EmbeddableContainer::class) {
				\assert($property->args !== null);
				$currentEntityMetadata = $property->args[EmbeddableContainer::class]['metadata'];
				$propertyPrefixTokens .= "$token->";
			} else {
				throw new InvalidArgumentException("Entity {$currentEntityMetadata->className}::\${$token} does not contain a relationship or an embeddable.");
			}
		}

		if ($makeDistinct) {
			$this->makeDistinct($builder, $this->mapper);
		}

		$propertyMetadata = $currentEntityMetadata->getProperty($lastToken);
		if ($propertyMetadata->wrapper === EmbeddableContainer::class) {
			$propertyExpression = \implode('->', \array_merge($tokens, [$lastToken]));
			throw new InvalidArgumentException("Property expression '$propertyExpression' does not fetch specific property.");
		}

		$column = $this->toColumnExpr(
			$currentEntityMetadata,
			$propertyMetadata,
			$currentReflection,
			$currentAlias,
			$propertyPrefixTokens
		);

		return new DbalExpressionResult(
			['%column', $column],
			false,
			function ($value) use ($propertyMetadata, $currentReflection) {
				return $this->normalizeValue($value, $propertyMetadata, $currentReflection);
			}
		);
	}


	/**
	 * @param array<string> $tokens
	 * @param mixed         $token
	 * @param int           $tokenIndex
	 * @return array{string, IConventions, EntityMetadata, DbalMapper}
	 */
	private function processRelationship(
		array $tokens,
		QueryBuilder $builder,
		PropertyMetadata $property,
		IConventions $currentReflection,
		DbalMapper $currentMapper,
		string $currentAlias,
		$token,
		int $tokenIndex,
		bool & $makeDistinct
	): array
	{
		\assert($property->relationship !== null);
		$targetMapper = $this->model->getRepository($property->relationship->repository)->getMapper();
		\assert($targetMapper instanceof DbalMapper);

		$targetReflection = $targetMapper->getConventions();
		$targetEntityMetadata = $property->relationship->entityMetadata;

		$relType = $property->relationship->type;
		if ($relType === Relationship::ONE_HAS_MANY) {
			\assert($property->relationship->property !== null);
			$toColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
			$fromColumn = $currentReflection->getStoragePrimaryKey()[0];
			$makeDistinct = true;

		} elseif ($relType === Relationship::ONE_HAS_ONE && !$property->relationship->isMain) {
			\assert($property->relationship->property !== null);
			$toColumn = $targetReflection->convertEntityToStorageKey($property->relationship->property);
			$fromColumn = $currentReflection->getStoragePrimaryKey()[0];

		} elseif ($relType === Relationship::MANY_HAS_MANY) {
			$toColumn = $targetReflection->getStoragePrimaryKey()[0];
			$fromColumn = $currentReflection->getStoragePrimaryKey()[0];
			$makeDistinct = true;

			if ($property->relationship->isMain) {
				\assert($currentMapper instanceof DbalMapper);
				[
					$joinTable,
					[$inColumn, $outColumn],
				] = $currentMapper->getManyHasManyParameters($property, $targetMapper);

			} else {
				\assert($currentMapper instanceof DbalMapper);
				\assert($property->relationship->property !== null);

				$sourceProperty = $targetEntityMetadata->getProperty($property->relationship->property);
				[
					$joinTable,
					[$outColumn, $inColumn],
				] = $targetMapper->getManyHasManyParameters($sourceProperty, $currentMapper);
			}

			$builder->leftJoin($currentAlias, "[$joinTable]", self::getAlias($joinTable), "[$currentAlias.$fromColumn] = [$joinTable.$inColumn]");
			$currentAlias = $joinTable;
			$fromColumn = $outColumn;

		} else {
			$toColumn = $targetReflection->getStoragePrimaryKey()[0];
			$fromColumn = $currentReflection->convertEntityToStorageKey($token);
		}

		$targetTable = $targetMapper->getTableName();
		$targetAlias = implode('_', array_slice($tokens, 0, $tokenIndex + 1));

		$builder->leftJoin($currentAlias, "[$targetTable]", $targetAlias, "[$currentAlias.$fromColumn] = [$targetAlias.$toColumn]");

		return [$targetAlias, $targetReflection, $targetEntityMetadata, $targetMapper];
	}


	/**
	 * @return string|array<string>
	 */
	private function toColumnExpr(
		EntityMetadata $entityMetadata,
		PropertyMetadata $propertyMetadata,
		IConventions $conventions,
		string $alias,
		string $propertyPrefixTokens
	)
	{
		if ($propertyMetadata->isPrimary && $propertyMetadata->isVirtual) { // primary-proxy
			$primaryKey = $entityMetadata->getPrimaryKey();
			if (count($primaryKey) > 1) { // composite primary key
				$pair = [];
				foreach ($primaryKey as $columnName) {
					$columnName = $conventions->convertEntityToStorageKey($propertyPrefixTokens . $columnName);
					$pair[] = "{$alias}.{$columnName}";
				}
				return $pair;
			} else {
				$propertyName = $primaryKey[0];
			}
		} else {
			$propertyName = $propertyMetadata->name;
		}

		$columnName = $conventions->convertEntityToStorageKey($propertyPrefixTokens . $propertyName);
		$columnExpr = "{$alias}.{$columnName}";
		return $columnExpr;
	}


	private function makeDistinct(QueryBuilder $builder, DbalMapper $mapper)
	{
		$baseTable = $builder->getFromAlias();
		if ($this->platformName === 'mssql') {
			$tableName = $mapper->getTableName();
			$columns = $mapper->getDatabasePlatform()->getColumns($tableName);
			$columnNames = \array_map(function ($column) use ($tableName) {
				return $tableName . '.'. $column['name'];
			}, $columns);
			$builder->groupBy('%column[]', $columnNames);

		} else {
			$primaryKey = $this->mapper->getConventions()->getStoragePrimaryKey();

			$groupBy = [];
			foreach ($primaryKey as $column) {
				$groupBy[] = "{$baseTable}.{$column}";
			}

			$builder->groupBy('%column[]', $groupBy);
		}
	}
}