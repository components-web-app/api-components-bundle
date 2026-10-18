<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\MessageHandler;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Message\PublishMercureUpdatesMessage;
use Silverback\ApiComponentsBundle\MessageHandler\PublishMercureUpdatesHandler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

class PublishMercureUpdatesHandlerTest extends TestCase
{
    /** @var list<string> */
    private array $published = [];

    private function hub(string $name): MockHub
    {
        return new MockHub('https://internal.example.com/.well-known/mercure', new StaticTokenProvider('publisher-jwt'), function (Update $update) use ($name): string {
            $this->published[] = $name . ' ' . ($update->isPrivate() ? 'private ' : 'public ') . $update->getTopics()[0];

            return 'urn:uuid:published';
        });
    }

    public function test_the_updates_are_published_in_the_order_they_were_given(): void
    {
        $default = $this->hub('default');
        $handler = new PublishMercureUpdatesHandler(new HubRegistry($default, ['default' => $default]));

        $handler(new PublishMercureUpdatesMessage([
            new Update('https://example.com/_/pages/1', '{"@id":"/_/pages/1"}'),
            new Update('https://example.com/_/pages/1?draft=1', '{"@id":"/_/pages/1","title":"t"}', true),
        ]));

        self::assertSame(['default public https://example.com/_/pages/1', 'default private https://example.com/_/pages/1?draft=1'], $this->published);
    }

    public function test_the_updates_go_to_the_named_hub(): void
    {
        $default = $this->hub('default');
        $handler = new PublishMercureUpdatesHandler(new HubRegistry($default, ['default' => $default, 'other' => $this->hub('other')]));

        $handler(new PublishMercureUpdatesMessage([new Update('https://example.com/_/pages/1', '{}')], 'other'));

        self::assertSame(['other public https://example.com/_/pages/1'], $this->published);
    }

    public function test_the_handler_is_registered_for_the_message(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services_doctrine_orm_mercure_publisher.php');

        $definition = $container->getDefinition('silverback.api_components.message_handler.publish_mercure_updates');

        self::assertSame(PublishMercureUpdatesHandler::class, $definition->getClass());
        self::assertSame([['handles' => PublishMercureUpdatesMessage::class]], $definition->getTag('messenger.message_handler'));
        self::assertSame(HubRegistry::class, (string) $definition->getArgument(0));
    }
}
