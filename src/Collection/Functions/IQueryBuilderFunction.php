<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Collection\Functions;

use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;


interface IQueryBuilderFunction
{
	/**
	 * Returns true if entity should stay in the result collection; the condition is evaluated in database and this
	 * method just returns appropriate Nexras Dbal's filtering expression for passed args.
	 * @param array<mixed> $args
	 * @return array<mixed> list of Nextras Dbal's filtering arguments
	 */
	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args
	): array;
}
