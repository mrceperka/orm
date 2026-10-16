<?php declare(strict_types = 1);

namespace NextrasTests\Orm\Integration\BridgeNetteDI;


use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\Extensions\ExtensionsExtension;
use NextrasTests\Orm\Author;
use Tester\Assert;


require_once __DIR__ . '/../../../bootstrap.php';


$cacheDir = TEMP_DIR . '/cache/bridge-nette-di-initialize-metadata';
$loader = new ContainerLoader($cacheDir);
$className = $loader->load(function (Compiler $compiler) use ($cacheDir): ?string {
	$compiler->addExtension('extensions', new ExtensionsExtension());
	$compiler->addConfig(['parameters' => ['tempDir' => $cacheDir]]);
	$compiler->loadConfig(__DIR__ . '/dic-initialize-metadata.neon');
	return null;
}, __FILE__);

/** @var Container $container */
$container = new $className();
$container->initialize();

// The model is never touched, entity metadata have to be available anyway.
Assert::noError(fn () => new Author());
