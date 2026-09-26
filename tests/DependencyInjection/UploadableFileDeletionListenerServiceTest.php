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

use Doctrine\ORM\Events;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableFileDeletionListener;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class UploadableFileDeletionListenerServiceTest extends TestCase
{
    public function test_the_listener_is_registered_for_on_flush_and_post_flush_with_an_optional_logger(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');

        $id = 'silverback.api_components.doctrine.event_listener.uploadable_file_deletion';
        $definition = $container->getDefinition($id);

        self::assertSame(UploadableFileDeletionListener::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(
            [['event' => Events::onFlush], ['event' => Events::postFlush]],
            $definition->getTag('doctrine.event_listener')
        );
        self::assertSame([['method' => 'reset']], $definition->getTag('kernel.reset'));

        $arguments = $definition->getArguments();
        self::assertCount(3, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(UploadableAttributeReader::class, (string) $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(UploadableFileManager::class, (string) $arguments[1]);
        self::assertInstanceOf(Reference::class, $arguments[2]);
        self::assertSame('logger', (string) $arguments[2]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $arguments[2]->getInvalidBehavior());

        self::assertSame($id, (string) $container->getAlias(UploadableFileDeletionListener::class));
    }
}
