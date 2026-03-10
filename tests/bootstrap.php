<?php
declare(strict_types=1);

use Psr\EventDispatcher\EventDispatcherInterface;
use SuperKernel\Attribute\Provider\AttributeCollectorProvider;
use SuperKernel\Di\Container;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container(new AttributeCollectorProvider()());

var_dump(
	$container->get(EventDispatcherInterface::class),
);