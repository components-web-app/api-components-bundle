<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass;

use Silverback\ApiComponentsBundle\Realtime\RealtimePublishClient;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Mercure\Hub;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RealtimeHubCompilerPass implements CompilerPassInterface
{
    public const string HUB_ID = 'silverback.api_components.realtime.hub';
    public const string HUB_NAME_PARAMETER = 'silverback.api_components.mercure.hub_name';
    public const string MERCURE_HUB_FACTORY = 'Symfony\Bundle\MercureBundle\HubFactory';
    private const int HTTP_CLIENT_ARGUMENT = 4;

    public function process(ContainerBuilder $container): void
    {
        $hubName = $container->getParameter(self::HUB_NAME_PARAMETER);
        $hubId = \is_string($hubName) ? \sprintf('mercure.hub.%s', $hubName) : (string) $container->getAlias(HubInterface::class);
        $hub = $container->findDefinition($hubId);

        if (!self::publishesOverHttp($hub)) {
            $container->setAlias(self::HUB_ID, $hubId);

            return;
        }

        $client = (new Definition(HttpClientInterface::class))
            ->setFactory([RealtimePublishClient::class, 'bound'])
            ->setArguments([$hub->getArgument(self::HTTP_CLIENT_ARGUMENT)]);

        $container->setDefinition(self::HUB_ID, (clone $hub)->setTags([])->replaceArgument(self::HTTP_CLIENT_ARGUMENT, $client));
    }

    private static function publishesOverHttp(Definition $hub): bool
    {
        return \array_key_exists(self::HTTP_CLIENT_ARGUMENT, $hub->getArguments())
            && (Hub::class === $hub->getClass() || [self::MERCURE_HUB_FACTORY, 'create'] === $hub->getFactory());
    }
}
