<?php declare(strict_types = 1);

/**
 * @phpVersion >= 8.4
 * @dataProvider dic-lazy.ini
 */

namespace NextrasTests\Orm\Integration\BridgeNetteDI;


use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\DIExtension;
use Nette\DI\Extensions\ExtensionsExtension;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Model\MetadataStorage;
use NextrasTests\Orm\Author;
use Tester\Assert;
use Tester\Environment;


require_once __DIR__ . '/../../../bootstrap.php';


$args = Environment::loadData();
$initializeMetadata = match ($args['initializeMetadata']) {
	'enabled' => true,
	'disabled' => false,
	default => null,
};

$cacheDir = TEMP_DIR . '/cache/bridge-nette-di-dic-lazy-' . $args['initializeMetadata'];
$loader = new ContainerLoader($cacheDir);
$className = $loader->load(function (Compiler $compiler) use ($cacheDir, $initializeMetadata): ?string {
	$compiler->addExtension('extensions', new ExtensionsExtension());
	$compiler->addExtension('di', new DIExtension());
	$compiler->addConfig(['parameters' => ['tempDir' => $cacheDir]]);
	$compiler->loadConfig(__DIR__ . '/dic-lazy.neon');
	if ($initializeMetadata !== null) {
		$compiler->addConfig(['nextras.orm' => ['initializeMetadata' => $initializeMetadata]]);
	}
	return null;
}, __FILE__ . ':' . $args['initializeMetadata']);

/** @var Container $container */
$container = new $className();
$container->initialize();

if ($initializeMetadata === false) {
	Assert::exception(fn () => new Author(), InvalidStateException::class, '%a%called too early%a%');
} else {
	// The model is never touched, entity metadata have to be available anyway.
	Assert::noError(fn () => new Author());
	Assert::type(EntityMetadata::class, MetadataStorage::get(Author::class));
}
