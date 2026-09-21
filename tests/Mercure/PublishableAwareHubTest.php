<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Mercure;

use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Mercure\PublishableAwareHub;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\RemoteHubInterface;
use Symfony\Component\Mercure\Update;

class PublishableAwareHubTest extends TestCase
{
    /** @var Update[] */
    private array $published = [];

    private function mockHub(mixed ...$arguments): MockHub
    {
        return new MockHub(
            'https://internal.example.com/.well-known/mercure',
            new StaticTokenProvider('publisher-jwt'),
            function (Update $update): string {
                $this->published[] = $update;

                return 'urn:uuid:published';
            },
            ...$arguments,
        );
    }

    private function buildHub(HubInterface $decorated, ?object $resource = null, bool $activePublishedAt = true, ?CwaCollectorData $collectorData = null): PublishableAwareHub
    {
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));
        $statusChecker->method('isActivePublishedAt')->willReturn($activePublishedAt);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        if ($resource) {
            $iriConverter->method('getResourceFromIri')->willReturn($resource);
        } else {
            $iriConverter->method('getResourceFromIri')->willThrowException(new ItemNotFoundException());
        }

        return new PublishableAwareHub($decorated, $statusChecker, $iriConverter, $collectorData);
    }

    private function update(bool $private = false): Update
    {
        return new Update(topics: ['https://example.com/component/1'], data: json_encode(['@id' => '/component/1']), private: $private, id: 'update-id', type: 'message', retry: 5);
    }

    public function test_it_is_a_remote_hub_so_decorating_one_hides_nothing(): void
    {
        self::assertInstanceOf(RemoteHubInterface::class, $this->buildHub($this->mockHub()));
    }

    public function test_it_forwards_the_urls_provider_and_factory_of_the_decorated_hub(): void
    {
        $factory = $this->createStub(TokenFactoryInterface::class);
        $decorated = $this->mockHub($factory, 'https://public.example.com/.well-known/mercure');
        $hub = $this->buildHub($decorated);

        self::assertSame('https://internal.example.com/.well-known/mercure', $hub->getUrl());
        self::assertSame('https://public.example.com/.well-known/mercure', $hub->getPublicUrl());
        self::assertSame($decorated->getProvider(), $hub->getProvider());
        self::assertSame($factory, $hub->getFactory());
    }

    #[RequiresMethod(HubInterface::class, 'getProtocolVersion')]
    public function test_it_forwards_the_protocol_version_of_the_decorated_hub(): void
    {
        $hub = $this->buildHub($this->mockHub(protocolVersion: ProtocolVersion::V1));

        self::assertSame(ProtocolVersion::V1, $hub->getProtocolVersion());
    }

    #[RequiresMethod(HubInterface::class, 'getCookieName')]
    public function test_it_forwards_the_cookie_name_of_the_decorated_hub(): void
    {
        $hub = $this->buildHub($this->mockHub(cookieName: 'customMercureCookie'));

        self::assertSame('customMercureCookie', $hub->getCookieName());
    }

    public function test_it_cannot_report_an_internal_url_for_a_hub_that_has_none(): void
    {
        $hub = $this->buildHub($this->createStub(HubInterface::class));

        $this->expectException(\LogicException::class);
        $hub->getUrl();
    }

    public function test_it_cannot_report_a_token_provider_for_a_hub_that_has_none(): void
    {
        $hub = $this->buildHub($this->createStub(HubInterface::class));

        $this->expectException(\LogicException::class);
        $hub->getProvider();
    }

    public function test_a_draft_publishable_resource_is_published_as_a_private_update(): void
    {
        $collectorData = new CwaCollectorData();
        $hub = $this->buildHub($this->mockHub(), new DummyPublishableComponent(), false, $collectorData);

        self::assertSame('urn:uuid:published', $hub->publish($this->update()));

        self::assertCount(1, $this->published);
        $update = $this->published[0];
        self::assertTrue($update->isPrivate());
        self::assertSame(['https://example.com/component/1'], $update->getTopics());
        self::assertSame(json_encode(['@id' => '/component/1']), $update->getData());
        self::assertSame('update-id', $update->getId());
        self::assertSame('message', $update->getType());
        self::assertSame(5, $update->getRetry());
        self::assertCount(1, $collectorData->getMercurePrivateUpgrades());
    }

    public function test_a_live_publishable_resource_is_published_unchanged(): void
    {
        $update = $this->update();
        $hub = $this->buildHub($this->mockHub(), new DummyPublishableComponent(), true);

        $hub->publish($update);

        self::assertSame([$update], $this->published);
    }

    public function test_a_resource_that_is_not_publishable_is_published_unchanged(): void
    {
        $update = $this->update();
        $hub = $this->buildHub($this->mockHub(), new \stdClass(), false);

        $hub->publish($update);

        self::assertSame([$update], $this->published);
    }

    public function test_an_update_for_a_resource_that_no_longer_exists_is_published_unchanged(): void
    {
        $update = $this->update();
        $hub = $this->buildHub($this->mockHub());

        $hub->publish($update);

        self::assertSame([$update], $this->published);
    }
}
