<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DependencyInjection\CompilerPass;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\ApiPlatformCompilerPass;
use Silverback\ApiComponentsBundle\Exception\UnroutedParentException;
use Silverback\ApiComponentsBundle\Helper\Collection\CollectionPopulator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class ApiPlatformCompilerPassTest extends TestCase
{
    private const string FLUSHER_ID = 'silverback.api_components.http_cache.flusher';

    public function test_the_flusher_receives_each_invalidation_url_with_its_client(): void
    {
        $container = $this->createContainer();
        $this->addInvalidationClient($container, 'api_platform.invalidation_http_client.0', 'http://localhost:2019/souin-api/souin');
        $this->addInvalidationClient($container, 'api_platform.invalidation_http_client.1', '%env(CACHE_URL)%');

        (new ApiPlatformCompilerPass())->process($container);

        self::assertEquals([
            ['url' => 'http://localhost:2019/souin-api/souin', 'client' => new Reference('api_platform.invalidation_http_client.0')],
            ['url' => '%env(CACHE_URL)%', 'client' => new Reference('api_platform.invalidation_http_client.1')],
        ], $container->getDefinition(self::FLUSHER_ID)->getArgument('$invalidationClients'));
    }

    public function test_scoped_clients_are_not_given_to_the_flusher(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('app.scoped_cache_client', (new Definition(ScopingHttpClient::class))->addTag('api_platform.http_cache.http_client'));

        (new ApiPlatformCompilerPass())->process($container);

        self::assertSame([], $container->getDefinition(self::FLUSHER_ID)->getArgument('$invalidationClients'));
    }

    public function test_the_collection_listener_receives_the_items_per_page_parameter_name(): void
    {
        $container = $this->createContainer();

        (new ApiPlatformCompilerPass())->process($container);

        self::assertSame('perPage', $container->getDefinition(CollectionPopulator::class)->getArgument('$itemsPerPageParameterName'));
    }

    public function test_the_api_platform_purge_listener_is_replaced_when_a_purger_is_configured(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('app.purger', new Definition());
        $container->setAlias('api_platform.http_cache.purger', 'app.purger');

        (new ApiPlatformCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition('api_platform.doctrine.listener.http_cache.purge'));
        self::assertTrue($container->hasDefinition('silverback.api_components.http_cache.purger'));
    }

    public function test_the_bundle_purger_is_removed_when_no_purger_is_configured(): void
    {
        $container = $this->createContainer();

        (new ApiPlatformCompilerPass())->process($container);

        self::assertTrue($container->hasDefinition('api_platform.doctrine.listener.http_cache.purge'));
        self::assertFalse($container->hasDefinition('silverback.api_components.http_cache.purger'));
    }

    public function test_the_api_platform_mercure_publish_listener_is_removed(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('api_platform.doctrine.orm.listener.mercure.publish', new Definition());

        (new ApiPlatformCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition('api_platform.doctrine.orm.listener.mercure.publish'));
    }

    public function test_api_platform_default_exception_statuses_are_appended_after_the_configured_ones(): void
    {
        $container = $this->createContainer();
        $container->setParameter('api_platform.exception_to_status', [
            UnroutedParentException::class => 422,
            NotNormalizableValueException::class => 422,
        ]);

        (new ApiPlatformCompilerPass())->process($container);

        $exceptionToStatus = $container->getParameter('api_platform.exception_to_status');
        self::assertSame([UnroutedParentException::class, NotNormalizableValueException::class], \array_slice(array_keys($exceptionToStatus), 0, 2));
        self::assertSame(400, $exceptionToStatus[SerializerExceptionInterface::class]);
        self::assertSame(400, $exceptionToStatus[InvalidArgumentException::class]);
        self::assertSame(409, $exceptionToStatus[OptimisticLockException::class]);
    }

    public function test_a_configured_status_for_a_default_exception_is_kept(): void
    {
        $container = $this->createContainer();
        $container->setParameter('api_platform.exception_to_status', [
            OptimisticLockException::class => 412,
        ]);

        (new ApiPlatformCompilerPass())->process($container);

        $exceptionToStatus = $container->getParameter('api_platform.exception_to_status');
        self::assertSame(412, $exceptionToStatus[OptimisticLockException::class]);
        self::assertSame(OptimisticLockException::class, array_key_first($exceptionToStatus));
        self::assertSame(400, $exceptionToStatus[SerializerExceptionInterface::class]);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('api_platform.collection.pagination.items_per_page_parameter_name', 'perPage');
        $container->setParameter('api_platform.exception_to_status', []);
        $container->setDefinition(CollectionPopulator::class, new Definition(CollectionPopulator::class));
        $container->setDefinition(self::FLUSHER_ID, new Definition());
        $container->setDefinition('api_platform.doctrine.listener.http_cache.purge', new Definition());
        $container->setDefinition('silverback.api_components.http_cache.purger', new Definition());

        return $container;
    }

    private function addInvalidationClient(ContainerBuilder $container, string $id, string $url): void
    {
        $definition = new Definition(ScopingHttpClient::class, [new Reference('http_client'), $url, ['base_uri' => $url]]);
        $definition->setFactory([ScopingHttpClient::class, 'forBaseUri']);
        $definition->addTag('api_platform.http_cache.http_client');
        $container->setDefinition($id, $definition);
    }
}
