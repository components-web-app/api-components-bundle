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
use Silverback\ApiComponentsBundle\Exception\UserDisabledException;
use Silverback\ApiComponentsBundle\Helper\Collection\CollectionPopulator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class ApiPlatformCompilerPassTest extends TestCase
{
    private const string FLUSHER_ID = 'silverback.api_components.http_cache.flusher';
    private const string BUNDLE_PURGER_ID = 'silverback.api_components.http_cache.purger';
    private const string BUFFERED_PURGER_ID = 'api_platform.http_cache.purger.buffered';

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

        (new ApiPlatformCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition('api_platform.doctrine.listener.http_cache.purge'));
        self::assertTrue($container->hasDefinition('silverback.api_components.http_cache.purger'));
    }

    public function test_the_bundle_purger_is_removed_when_no_purger_is_configured(): void
    {
        $container = $this->createContainer();
        $container->removeAlias('api_platform.http_cache.purger');

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
            UserDisabledException::class => 401,
            NotNormalizableValueException::class => 422,
        ]);

        (new ApiPlatformCompilerPass())->process($container);

        $exceptionToStatus = $container->getParameter('api_platform.exception_to_status');
        self::assertSame([UserDisabledException::class, NotNormalizableValueException::class], \array_slice(array_keys($exceptionToStatus), 0, 2));
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

    public function test_the_bundle_purges_through_the_purger_api_platform_buffers_so_a_failure_reaches_it_during_the_request(): void
    {
        $container = $this->createContainer();
        $this->addBufferedPurger($container, null);

        (new ApiPlatformCompilerPass())->process($container);

        self::assertEquals(new Reference('api_platform.http_cache.purger.buffered.inner'), $container->getDefinition(self::BUNDLE_PURGER_ID)->getArgument(2));
        self::assertEquals(new Reference('api_platform.http_cache.purger.buffered.inner'), $container->getDefinition(self::FLUSHER_ID)->getArgument(0));
    }

    public function test_a_renamed_inner_purger_of_the_buffer_is_the_one_the_bundle_purges_through(): void
    {
        $container = $this->createContainer();
        $this->addBufferedPurger($container, 'app.unbuffered_purger');

        (new ApiPlatformCompilerPass())->process($container);

        self::assertEquals(new Reference('app.unbuffered_purger'), $container->getDefinition(self::BUNDLE_PURGER_ID)->getArgument(2));
        self::assertEquals(new Reference('app.unbuffered_purger'), $container->getDefinition(self::FLUSHER_ID)->getArgument(0));
    }

    public function test_the_bundle_purges_through_the_configured_purger_when_api_platform_buffers_nothing(): void
    {
        $container = $this->createContainer();

        (new ApiPlatformCompilerPass())->process($container);

        self::assertEquals(new Reference('api_platform.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(self::BUNDLE_PURGER_ID)->getArgument(2));
        self::assertEquals(new Reference('api_platform.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(self::FLUSHER_ID)->getArgument(0));
    }

    public function test_a_buffered_purger_service_that_decorates_nothing_is_not_bypassed(): void
    {
        $container = $this->createContainer();
        $container->setDefinition(self::BUFFERED_PURGER_ID, new Definition());

        (new ApiPlatformCompilerPass())->process($container);

        self::assertEquals(new Reference('api_platform.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(self::BUNDLE_PURGER_ID)->getArgument(2));
        self::assertEquals(new Reference('api_platform.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(self::FLUSHER_ID)->getArgument(0));
    }

    public function test_the_flusher_bypasses_the_buffer_when_the_bundle_purger_was_removed(): void
    {
        $container = $this->createContainer();
        $container->removeAlias('api_platform.http_cache.purger');
        $this->addBufferedPurger($container, null);

        (new ApiPlatformCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition(self::BUNDLE_PURGER_ID));
        self::assertEquals(new Reference('api_platform.http_cache.purger.buffered.inner'), $container->getDefinition(self::FLUSHER_ID)->getArgument(0));
    }

    public function test_a_buffered_purger_is_left_alone_when_the_bundle_http_cache_services_are_not_loaded(): void
    {
        $container = $this->createContainer();
        $container->removeDefinition(self::FLUSHER_ID);
        $container->removeDefinition(self::BUNDLE_PURGER_ID);
        $this->addBufferedPurger($container, null);

        (new ApiPlatformCompilerPass())->process($container);

        self::assertFalse($container->hasDefinition(self::FLUSHER_ID));
        self::assertFalse($container->hasDefinition(self::BUNDLE_PURGER_ID));
        self::assertTrue($container->hasDefinition(self::BUFFERED_PURGER_ID));
    }

    private function addBufferedPurger(ContainerBuilder $container, ?string $innerId): void
    {
        $definition = new Definition('ApiPlatform\\HttpCache\\BufferedPurger');
        $definition->setDecoratedService('api_platform.http_cache.purger', $innerId);
        $container->setDefinition(self::BUFFERED_PURGER_ID, $definition);
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('api_platform.collection.pagination.items_per_page_parameter_name', 'perPage');
        $container->setParameter('api_platform.exception_to_status', []);
        $container->setDefinition(CollectionPopulator::class, new Definition(CollectionPopulator::class));
        $container->setDefinition(self::FLUSHER_ID, new Definition(null, [self::purgerReference(), '$invalidationClients' => []]));
        $container->setDefinition('api_platform.doctrine.listener.http_cache.purge', new Definition());
        $container->setDefinition(self::BUNDLE_PURGER_ID, new Definition(null, [new Reference('api_platform.iri_converter'), new Reference('api_platform.resource_class_resolver'), self::purgerReference()]));
        $container->setDefinition('app.purger', new Definition());
        $container->setAlias('api_platform.http_cache.purger', 'app.purger');

        return $container;
    }

    private static function purgerReference(): Reference
    {
        return new Reference('api_platform.http_cache.purger', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }

    private function addInvalidationClient(ContainerBuilder $container, string $id, string $url): void
    {
        $definition = new Definition(ScopingHttpClient::class, [new Reference('http_client'), $url, ['base_uri' => $url]]);
        $definition->setFactory([ScopingHttpClient::class, 'forBaseUri']);
        $definition->addTag('api_platform.http_cache.http_client');
        $container->setDefinition($id, $definition);
    }
}
