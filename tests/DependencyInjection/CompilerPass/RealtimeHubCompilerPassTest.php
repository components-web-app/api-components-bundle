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

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass\RealtimeHubCompilerPass;
use Silverback\ApiComponentsBundle\Realtime\RealtimePublishClient;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RealtimeHubCompilerPassTest extends TestCase
{
    public function test_the_realtime_hub_is_the_default_hub_as_mercure_bundle_defines_it_publishing_through_a_bounded_copy_of_its_http_client(): void
    {
        $container = self::container(null);
        $hub = (new Definition(HubInterface::class))
            ->setFactory([RealtimeHubCompilerPass::MERCURE_HUB_FACTORY, 'create'])
            ->setArguments(['https://hub.example/.well-known/mercure', new Reference('mercure.hub.default.jwt.provider'), null, 'https://example.com/.well-known/mercure', new Reference('http_client', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), null, 'v1'])
            ->addTag('mercure.hub');
        $container->setDefinition('mercure.hub.default', $hub);
        $container->setAlias(HubInterface::class, 'mercure.hub.default');

        (new RealtimeHubCompilerPass())->process($container);

        $realtime = $container->getDefinition(RealtimeHubCompilerPass::HUB_ID);
        self::assertNotSame($hub, $realtime);
        self::assertSame([RealtimeHubCompilerPass::MERCURE_HUB_FACTORY, 'create'], $realtime->getFactory());
        self::assertSame([], $realtime->getTags());
        self::assertSame(['https://hub.example/.well-known/mercure', 'https://example.com/.well-known/mercure', null, 'v1'], [$realtime->getArgument(0), $realtime->getArgument(3), $realtime->getArgument(5), $realtime->getArgument(6)]);
        self::assertSame('mercure.hub.default.jwt.provider', (string) $realtime->getArgument(1));
        $client = $realtime->getArgument(4);
        self::assertInstanceOf(Definition::class, $client);
        self::assertSame(HttpClientInterface::class, $client->getClass());
        self::assertSame([RealtimePublishClient::class, 'bound'], $client->getFactory());
        self::assertSame('http_client', (string) $client->getArgument(0));
        self::assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $client->getArgument(0)->getInvalidBehavior());
        self::assertSame('http_client', (string) $hub->getArgument(4));
        self::assertSame(['mercure.hub' => [[]]], $hub->getTags());
    }

    public function test_a_hub_defined_by_class_with_its_own_http_client_is_bounded_too(): void
    {
        $container = self::container('other');
        $container->setDefinition('mercure.hub.other', new Definition(Hub::class, ['https://hub.example/.well-known/mercure', new Reference('provider'), null, null, new Reference('app.mercure_client')]));

        (new RealtimeHubCompilerPass())->process($container);

        $realtime = $container->getDefinition(RealtimeHubCompilerPass::HUB_ID);
        self::assertSame(Hub::class, $realtime->getClass());
        self::assertSame('app.mercure_client', (string) $realtime->getArgument(4)->getArgument(0));
    }

    public function test_the_configured_hub_name_chooses_the_hub(): void
    {
        $container = self::container('other');
        $container->setDefinition('mercure.hub.default', new Definition(HubStub::class));
        $container->setAlias(HubInterface::class, 'mercure.hub.default');
        $container->setDefinition('mercure.hub.other', new Definition(HubStub::class));

        (new RealtimeHubCompilerPass())->process($container);

        self::assertSame('mercure.hub.other', (string) $container->getAlias(RealtimeHubCompilerPass::HUB_ID));
    }

    public function test_a_hub_the_application_defines_itself_is_used_as_it_is(): void
    {
        $container = self::container(null);
        $container->setDefinition('mercure.hub.default', new Definition(HubStub::class, ['$factory' => new Reference('factory')]));
        $container->setAlias(HubInterface::class, 'mercure.hub.default');

        (new RealtimeHubCompilerPass())->process($container);

        self::assertSame('mercure.hub.default', (string) $container->getAlias(RealtimeHubCompilerPass::HUB_ID));
        self::assertFalse($container->hasDefinition(RealtimeHubCompilerPass::HUB_ID));
    }

    public function test_a_hub_with_no_http_client_argument_is_used_as_it_is(): void
    {
        $container = self::container(null);
        $container->setDefinition('mercure.hub.default', new Definition(Hub::class, ['https://hub.example/.well-known/mercure', new Reference('provider')]));
        $container->setAlias(HubInterface::class, 'mercure.hub.default');

        (new RealtimeHubCompilerPass())->process($container);

        self::assertSame('mercure.hub.default', (string) $container->getAlias(RealtimeHubCompilerPass::HUB_ID));
    }

    public function test_a_hub_built_by_another_factory_is_used_as_it_is(): void
    {
        $container = self::container(null);
        $container->setDefinition('mercure.hub.default', (new Definition(HubInterface::class))->setFactory(['App\HubFactory', 'create'])->setArguments([1, 2, 3, 4, 5]));
        $container->setAlias(HubInterface::class, 'mercure.hub.default');

        (new RealtimeHubCompilerPass())->process($container);

        self::assertSame('mercure.hub.default', (string) $container->getAlias(RealtimeHubCompilerPass::HUB_ID));
    }

    private static function container(?string $hubName): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(RealtimeHubCompilerPass::HUB_NAME_PARAMETER, $hubName);

        return $container;
    }
}
