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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\CollectionSerializeStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ComponentPositionRemovalStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\DeletedResourceStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\FormSerializeStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\PublishableWriteStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RouteRedirectStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\UploadableWriteStateProcessor;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\UserNotificationStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ComponentUsageStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\DenyAccessStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\DownloadStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\PublishableDeserializeStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\PublishableReadStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\RouteGenerateStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UploadStateProvider;
use Silverback\ApiComponentsBundle\Helper\Collection\CollectionPopulator;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableDraftMerger;
use Silverback\ApiComponentsBundle\Helper\User\UserWriteNotifier;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class StateDecoratorServicesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     */
    public static function decorators(): iterable
    {
        yield 'deny access after the read and its security check' => [
            'silverback.api_components.api_platform.state_provider.deny_access',
            DenyAccessStateProvider::class,
            'api_platform.state_provider.read',
            -20,
        ];
        yield 'component usage outside deny access, so a denied component shows no usage' => [
            'silverback.api_components.api_platform.state_provider.component_usage',
            ComponentUsageStateProvider::class,
            'api_platform.state_provider.read',
            -40,
        ];
        yield 'user notification after the persist, outside uploadable and inside publishable' => [
            'silverback.api_components.api_platform.state_processor.user_notification',
            UserNotificationStateProcessor::class,
            'api_platform.state_processor.locator',
            15,
        ];
        yield 'route generation after validation and its security check' => [
            'silverback.api_components.api_platform.state_provider.route_generate',
            RouteGenerateStateProvider::class,
            'api_platform.state_provider.validate',
            -10,
        ];
        yield 'route redirects innermost, straight after the persist' => [
            'silverback.api_components.api_platform.state_processor.route_redirect',
            RouteRedirectStateProcessor::class,
            'api_platform.state_processor.locator',
            50,
        ];
        yield 'delete cascade before the remove, after publishable and uploadable' => [
            'silverback.api_components.api_platform.state_processor.deleted_resource',
            DeletedResourceStateProcessor::class,
            'api_platform.state_processor.locator',
            30,
        ];
        yield 'component position removal before the remove, after the delete cascade' => [
            'silverback.api_components.api_platform.state_processor.component_position_removal',
            ComponentPositionRemovalStateProcessor::class,
            'api_platform.state_processor.locator',
            40,
        ];
        yield 'publishable merge on read between deny access and component usage' => [
            'silverback.api_components.api_platform.state_provider.publishable_read',
            PublishableReadStateProvider::class,
            'api_platform.state_provider.read',
            -30,
        ];
        yield 'publication date guard after deserialization and its security check' => [
            'silverback.api_components.api_platform.state_provider.publishable_deserialize',
            PublishableDeserializeStateProvider::class,
            'api_platform.state_provider.deserialize',
            -10,
        ];
        yield 'publishable merge outermost on the write, so the merged resource is what is persisted' => [
            'silverback.api_components.api_platform.state_processor.publishable_write',
            PublishableWriteStateProcessor::class,
            'api_platform.state_processor.locator',
            10,
        ];
        yield 'uploadable files around the persist, inside publishable' => [
            'silverback.api_components.api_platform.state_processor.uploadable_write',
            UploadableWriteStateProcessor::class,
            'api_platform.state_processor.locator',
            20,
        ];
        yield 'upload outermost on the read, so the read resource has passed every access check' => [
            'silverback.api_components.api_platform.state_provider.upload',
            UploadStateProvider::class,
            'api_platform.state_provider.read',
            -50,
        ];
        yield 'download outermost on the read, so the read resource has passed every access check' => [
            'silverback.api_components.api_platform.state_provider.download',
            DownloadStateProvider::class,
            'api_platform.state_provider.read',
            -50,
        ];
        yield 'collection filled before serialization, inside the form view' => [
            'silverback.api_components.api_platform.state_processor.collection_serialize',
            CollectionSerializeStateProcessor::class,
            'api_platform.state_processor.serialize',
            20,
        ];
        yield 'form view and submit before serialization' => [
            'silverback.api_components.api_platform.state_processor.form_serialize',
            FormSerializeStateProcessor::class,
            'api_platform.state_processor.serialize',
            10,
        ];
    }

    #[DataProvider('decorators')]
    public function test_the_decorator_wraps_a_service_that_exists_whether_or_not_symfony_listeners_are_used(string $id, string $class, string $decorated, int $priority): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');

        $definition = $container->getDefinition($id);

        self::assertSame($class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame([$decorated, null, $priority], $definition->getDecoratedService());
        $inner = $definition->getArgument(0);
        self::assertInstanceOf(Reference::class, $inner);
        self::assertSame($id . '.inner', (string) $inner);
        self::assertSame($id, (string) $container->getAlias($class));
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function helpers(): iterable
    {
        yield 'draft merger' => ['silverback.api_components.helper.publishable.draft_merger', PublishableDraftMerger::class];
        yield 'user write notifier' => ['silverback.api_components.helper.user.write_notifier', UserWriteNotifier::class];
        yield 'collection populator' => ['silverback.api_components.helper.collection.populator', CollectionPopulator::class];
    }

    #[DataProvider('helpers')]
    public function test_the_helpers_the_decorators_call_are_plain_services_not_listeners(string $id, string $class): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');

        $definition = $container->getDefinition($id);

        self::assertSame($class, $definition->getClass());
        self::assertSame([], $definition->getTags());
        self::assertSame($id, (string) $container->getAlias($class));
    }
}
