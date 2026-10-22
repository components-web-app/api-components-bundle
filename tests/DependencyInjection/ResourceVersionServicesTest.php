<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DependencyInjection;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\EventListener\Api\ResourceVersionResponseListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\ResourceVersionListener;
use Silverback\ApiComponentsBundle\Helper\SubRequest\SubRequestKernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class ResourceVersionServicesTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        (new PhpFileLoader($this->container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');
    }

    public function test_versions_are_claimed_on_flush_after_draft_references_are_moved_and_before_the_write_is_propagated(): void
    {
        $definition = $this->container->getDefinition('silverback.api_components.doctrine.event_listener.resource_version');

        self::assertSame(ResourceVersionListener::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertEquals([new Reference(PublishableAttributeReader::class)], $definition->getArguments());
        self::assertSame([['event' => 'onFlush', 'priority' => 50]], $definition->getTag('doctrine.event_listener'));
        self::assertSame([['method' => 'reset']], $definition->getTag('kernel.reset'));
        self::assertSame('silverback.api_components.doctrine.event_listener.resource_version', (string) $this->container->getAlias(ResourceVersionListener::class));
    }

    public function test_the_response_listener_sets_the_etag_and_answers_a_conflict(): void
    {
        $definition = $this->container->getDefinition('silverback.api_components.event_listener.api.resource_version_response');

        self::assertSame(ResourceVersionResponseListener::class, $definition->getClass());
        self::assertEquals([new Reference(TokenStorageInterface::class), new Reference(ManagerRegistry::class), new Reference(SubRequestKernel::class)], $definition->getArguments());
        self::assertSame([
            ['event' => ResponseEvent::class, 'priority' => -10, 'method' => 'onKernelResponse'],
            ['event' => ExceptionEvent::class, 'priority' => 20, 'method' => 'onKernelException'],
        ], $definition->getTag('kernel.event_listener'));
    }
}
