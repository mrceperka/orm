<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Functions;

use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ConditionParserHelper;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;


class DisjunctionOperatorFunction implements IArrayFunction, IQueryBuilderFunction
{
	public function processArrayExpression(ArrayCollectionHelper $helper, IEntity $entity, array $args)
	{
		foreach ($this->normalizeFunctions($args) as $arg) {
			$callback = $helper->createFilter($arg);
			if ($callback($entity)) {
				return true;
			}
		}
		return false;
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args
	): array
	{
		$processedArgs = [];
		foreach ($this->normalizeFunctions($args) as $arg) {
			$processedArgs[] = $helper->processFilterFunction($builder, $arg);
		}
		return ['%or', $processedArgs];
	}


	private function normalizeFunctions(array $args): array
	{
		if (isset($args[0])) {
			return $args;
		}

		$processedArgs = [];
		foreach ($args as $argName => $argValue) {
			[$argName, $operator] = ConditionParserHelper::parsePropertyOperator($argName);
			$processedArgs[] = [ValueOperatorFunction::class, $operator, $argName, $argValue];
		}
		return $processedArgs;
	}
}
