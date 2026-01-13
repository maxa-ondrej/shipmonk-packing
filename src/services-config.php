<?php

declare(strict_types=1);

use App\Entity\Packaging;
use App\Entity\PackagingResult;
use App\Repository\PackagingRepository;
use App\Repository\PackagingResultRepository;
use App\Service\BinPackingClient\BinPackingConfig;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use JsonMapper\JsonMapper;
use JsonMapper\JsonMapperFactory;
use JsonMapper\JsonMapperInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    'app.name' => 'Packing Service',
    'app.path' => __DIR__,
    'app.devMode' => true,
    'db.params' => [
        'driver' => DI\env('DB_DRIVER'),
        'host' => DI\env('DB_HOST'),
        'user' => DI\env('DB_USER'),
        'password' => DI\env('DB_PASSWORD'),
        'dbname' => DI\env('DB_NAME'),
    ],
    'db.paths' => [
        DI\string('{app.path}/Entity'),
    ],
    Connection::class => DI\factory([DriverManager::class, 'getConnection'])
        ->parameter('params', DI\get('db.params')),
    Configuration::class => static function (ContainerInterface $container) {
        /** @var string[] $paths */
        $paths = $container->get('db.paths');

        /** @var bool $devMode */
        $devMode = $container->get('app.devMode');
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, $devMode);
        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        return $config;
    },
    JsonMapper::class => DI\factory([JsonMapperFactory::class, 'bestFit']),
    JsonMapperInterface::class => DI\get(JsonMapper::class),
    'logger.handlers' => [
        new StreamHandler('log/error.log', Level::Error)->setFormatter(new JsonFormatter()),
        new StreamHandler('log/info.log', Level::Info)->setFormatter(new JsonFormatter()),
    ],
    Logger::class => DI\autowire()
        ->constructorParameter('name', 'packing')
        ->constructorParameter('handlers', DI\get('logger.handlers')),
    LoggerInterface::class => DI\get(Logger::class),
    BinPackingConfig::class => DI\autowire()
        ->constructorParameter('username', DI\env('BIN_PACKING_USERNAME'))
        ->constructorParameter('apiKey', DI\env('BIN_PACKING_API_KEY'))
        ->constructorParameter('apiUrl', DI\env('BIN_PACKING_API_URL')),
    'doctrine.entity_manager' => DI\get(EntityManager::class),
    PackagingRepository::class => DI\factory([EntityManager::class, 'getRepository'])
        ->parameter('className', Packaging::class),
    PackagingResultRepository::class => DI\factory([EntityManager::class, 'getRepository'])
        ->parameter('className', PackagingResult::class),
];
