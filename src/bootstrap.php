<?php

declare(strict_types=1);

use DI\ContainerBuilder;

require __DIR__.'/../vendor/autoload.php';

$builder = new ContainerBuilder();
$builder->useAttributes(false);
$builder->useAutowiring(true);
$builder->addDefinitions(__DIR__.'/services-config.php');

return $builder->build();
