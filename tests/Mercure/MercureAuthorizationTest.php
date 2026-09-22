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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MercureAuthorizationTest extends TestCase
{
    public function test_a_resource_with_no_operation_is_left_out_of_the_subscribe_topics(): void
    {
        $resourceNames = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNames->method('create')->willReturn(new ResourceNameCollection([Page::class, Layout::class]));

        $metadata = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadata->method('create')->willReturnCallback(static fn (string $resourceClass): ResourceMetadataCollection => Layout::class === $resourceClass
            ? new ResourceMetadataCollection(Layout::class, [new ApiResource(operations: [new Get(uriTemplate: '/layouts/{id}', class: Layout::class, mercure: true)])])
            : new ResourceMetadataCollection(Page::class, []));

        $hub = new MockHub('https://example.com/.well-known/mercure', new StaticTokenProvider('jwt'), static fn (): string => 'id');

        $authorization = new MercureAuthorization(
            $resourceNames,
            $metadata,
            $this->createStub(PublishableStatusChecker::class),
            new RequestContext(host: 'example.com'),
            new Authorization(new HubRegistry($hub)),
            new RequestStack(),
            $this->createStub(AuthorizationCheckerInterface::class),
        );

        self::assertSame(['http://example.com/layouts/{id}'], $authorization->getSubscribeTopics());
    }
}
