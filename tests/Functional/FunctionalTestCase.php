<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as LegacyApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client as LegacyClient;
use ApiPlatform\Test\ApiTestCase;
use ApiPlatform\Test\Client;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\RejectedDeleteMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\UnreachableDatabaseMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\KernelHttpClient;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\SwitchableMailer;
use Symfony\Component\HttpKernel\KernelInterface;

if (!class_exists(ApiTestCase::class)) {
    class_alias(LegacyApiTestCase::class, ApiTestCase::class);
    class_alias(LegacyClient::class, Client::class);
}

abstract class FunctionalTestCase extends ApiTestCase
{
    private const array RESET_ENV = ['EMAIL_LINK_DEFAULT_ORIGIN', 'ORPHANED_RESOURCES_NOTIFY_RECIPIENTS'];

    private const string UPLOADS_SUBDIRECTORY_ENV = 'TEST_UPLOADS_SUBDIRECTORY';

    protected static ?bool $alwaysBootKernel = false;

    private static bool $schemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER[self::UPLOADS_SUBDIRECTORY_ENV] = $_ENV[self::UPLOADS_SUBDIRECTORY_ENV] = '/phpunit-' . getmypid();
        self::resetTestDoubles();
        StaticDriver::setKeepStaticConnections(true);
        self::createSchemaOnce();
        StaticDriver::beginTransaction();
    }

    protected function tearDown(): void
    {
        StaticDriver::rollBack();
        self::resetTestDoubles();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $kernelOptions
     * @param array<string, mixed> $defaultOptions
     */
    protected static function createClient(array $kernelOptions = [], array $defaultOptions = []): Client
    {
        return parent::createClient($kernelOptions, $defaultOptions + ['base_uri' => 'http://example.com']);
    }

    /**
     * @param array{environment?: string, debug?: bool, routePrefix?: string, profilerCollect?: bool} $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        static::$class ??= static::getKernelClass();

        $environment = $options['environment'] ?? $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'test';
        $debug = $options['debug'] ?? (bool) ($_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? false);

        return new static::$class($environment, $debug, $options['routePrefix'] ?? '', $options['profilerCollect'] ?? true);
    }

    protected static function getEntityManager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get('doctrine')->getManager();
        \assert($manager instanceof EntityManagerInterface);

        return $manager;
    }

    protected static function rebootKernel(): void
    {
        if (null === static::$kernel) {
            return;
        }

        static::$kernel->shutdown();
        static::$kernel->boot();
    }

    protected static function uploadsDirectory(): string
    {
        return __DIR__ . '/app/public/uploads' . \AppKernel::shardSuffix() . $_SERVER[self::UPLOADS_SUBDIRECTORY_ENV];
    }

    protected static function bootDebugKernel(): KernelInterface
    {
        return static::bootKernel(['debug' => true, 'profilerCollect' => false]);
    }

    private static function createSchemaOnce(): void
    {
        if (self::$schemaCreated) {
            return;
        }

        $manager = static::getEntityManager();
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        StaticDriver::commit();
        self::$schemaCreated = true;
        static::ensureKernelShutdown();
    }

    private static function resetTestDoubles(): void
    {
        HubStub::setUnreachable(false);
        HubStub::clearPublished();
        UnreachableDatabaseMiddleware::setUnreachable(false);
        RejectedDeleteMiddleware::setRejectDeletes(false);
        MockClientCallback::setCacheUnreachable(false);
        SwitchableMailer::setUnreachable(false);
        KernelHttpClient::reset();
        foreach (self::RESET_ENV as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
        }
    }
}
