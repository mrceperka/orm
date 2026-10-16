<?php declare(strict_types = 1);

/**
 * @phpVersion >= 8.4
 */

namespace NextrasTests\Orm\Integration\BridgeNetteDI;


use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\DIExtension;
use Nette\DI\Extensions\ExtensionsExtension;
use Nextras\Orm\Model\IModel;
use Nextras\Orm\Repository\IRepository;
use NextrasTests\Orm\Tag;
use NextrasTests\Orm\TagsRepository;
use NextrasTests\Orm\TimeSeries;
use NextrasTests\Orm\TimeSeriesRepository;
use ReflectionClass;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';

function buildDic(string $config): Container
{
	$cacheDir = TEMP_DIR . '/cache/bridge-nette-di-dic-lazy-flush';
	$loader = new ContainerLoader($cacheDir);
	$key = __FILE__ . ':' . __LINE__ . ':' . $config;
	$className = $loader->load(function (Compiler $compiler) use ($config, $cacheDir): ?string {
		$compiler->addExtension('extensions', new ExtensionsExtension());
		$compiler->addExtension('di', new DIExtension());
		$compiler->addConfig(['parameters' => ['tempDir' => $cacheDir]]);
		$compiler->loadConfig($config);
		return null;
	}, $key);

	/** @var Container $dic */
	$dic = new $className();
	return $dic;
}


function isLazy(object $object): bool
{
	return (new ReflectionClass($object))->isUninitializedLazyObject($object);
}


/**
 * Resolves all repository services, so they exist in the container as uninitialized lazy ghosts.
 * @return array<class-string, IRepository<*>>
 */
function resolveRepositories(Container $container): array
{
	$repositories = [];
	foreach ($container->findByType(IRepository::class) as $name) {
		$repository = $container->getService($name);
		assert($repository instanceof IRepository);
		Assert::true(isLazy($repository), $name);
		Assert::true($container->isCreated($name), $name);
		$repositories[$repository::class] = $repository;
	}
	return $repositories;
}


/**
 * @param array<class-string, IRepository<*>> $repositories
 * @param list<class-string> $used
 */
function assertOnlyUsedInitialized(array $repositories, array $used): void
{
	foreach ($repositories as $class => $repository) {
		Assert::same(!in_array($class, $used, true), isLazy($repository), $class);
	}
}


// Unused lazy repositories stay uninitialized during flush, refreshAll & clear.
(function (): void {
	$container = buildDic(__DIR__ . '/dic-lazy-flush.neon');
	$repositories = resolveRepositories($container);
	Assert::true(count($repositories) > 5);

	$model = $container->getByType(IModel::class);
	$timeSeriesRepository = $model->getRepository(TimeSeriesRepository::class);

	$timeSeries = new TimeSeries();
	$timeSeries->date = 'now';
	$timeSeries->value = 1;
	$model->persist($timeSeries);

	$flushed = [];
	$timeSeriesRepository->onFlush[] = function (array $persisted) use (&$flushed): void {
		$flushed = $persisted;
	};

	$model->flush();
	Assert::true($timeSeries->isPersisted());
	Assert::same([$timeSeries], $flushed);
	Assert::same(1, $timeSeriesRepository->findAll()->countStored());
	assertOnlyUsedInitialized($repositories, [TimeSeriesRepository::class]);

	$model->refreshAll();
	assertOnlyUsedInitialized($repositories, [TimeSeriesRepository::class]);

	$model->clear();
	Assert::false($timeSeries->isAttached());
	assertOnlyUsedInitialized($repositories, [TimeSeriesRepository::class]);
	Assert::same(1, $timeSeriesRepository->findAll()->countStored());
})();


// A lazy repository initialized later is flushed by the model.
(function (): void {
	$container = buildDic(__DIR__ . '/dic-lazy-flush.neon');
	$repositories = resolveRepositories($container);
	$model = $container->getByType(IModel::class);

	$model->flush();
	assertOnlyUsedInitialized($repositories, []);

	$tag = new Tag('lazy');
	$model->persist($tag);
	assertOnlyUsedInitialized($repositories, [TagsRepository::class]);

	$model->flush();
	Assert::true($tag->isPersisted());
	Assert::same(1, $model->getRepository(TagsRepository::class)->findAll()->countStored());
	assertOnlyUsedInitialized($repositories, [TagsRepository::class]);
})();
