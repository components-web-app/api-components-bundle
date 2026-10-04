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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MercureAuthorizationTest extends TestCase
{
    public function test_a_resource_with_no_operation_is_left_out_of_the_subscribe_topics(): void
    {
        $authorization = $this->createAuthorization(
            [
                Page::class => [],
                Layout::class => [new Get(uriTemplate: '/layouts/{id}', class: Layout::class, name: 'layout_get', mercure: true)],
            ],
            ['layout_get' => '/layouts/{id}'],
        );

        self::assertSame(['http://example.com/layouts/:id\\?'], $authorization->getSubscribeTopics());
    }

    public function test_the_subscribe_topic_includes_the_prefix_the_application_imports_api_platform_routes_under(): void
    {
        $authorization = $this->createAuthorization(
            [Layout::class => [new Get(uriTemplate: '/layouts/{id}{._format}', class: Layout::class, name: 'layout_get', routePrefix: '/_', mercure: true)]],
            ['layout_get' => '/_api/_/layouts/{id}.{_format}'],
        );

        self::assertSame(['http://example.com/_api/_/layouts/:id\\?'], $authorization->getSubscribeTopics());
    }

    public function test_the_draft_topic_of_a_publishable_resource_includes_the_application_route_prefix(): void
    {
        $authorization = $this->createAuthorization(
            [DummyPublishableComponent::class => [new Get(uriTemplate: '/dummy_publishable_components/{id}{._format}', class: DummyPublishableComponent::class, name: 'publishable_get', routePrefix: '/component', mercure: true)]],
            ['publishable_get' => '/_api/component/dummy_publishable_components/{id}.{_format}'],
            true,
        );

        self::assertSame(
            [
                'http://example.com/_api/component/dummy_publishable_components/:id\\?',
                'http://example.com/_api/component/dummy_publishable_components/:id\\?draft=1',
            ],
            $authorization->getSubscribeTopics(),
        );
    }

    public function test_the_subscribe_topic_is_built_from_the_operation_when_its_route_is_not_registered(): void
    {
        $authorization = $this->createAuthorization(
            [Layout::class => [new Get(uriTemplate: '/layouts/{id}{._format}', class: Layout::class, name: 'layout_get', routePrefix: '/_', mercure: true)]],
            ['another_route' => '/_api/_/layouts/{id}.{_format}'],
        );

        self::assertSame(['http://example.com/_/layouts/:id\\?'], $authorization->getSubscribeTopics());
    }

    public function test_the_subscribe_topic_is_built_from_the_operation_when_it_has_no_route_name(): void
    {
        $authorization = $this->createAuthorization(
            [Layout::class => [new Get(uriTemplate: '/layouts/{id}{._format}', class: Layout::class, routePrefix: '/_', mercure: true)]],
            ['layout_get' => '/_api/_/layouts/{id}.{_format}'],
        );

        self::assertSame(['http://example.com/_/layouts/:id\\?'], $authorization->getSubscribeTopics());
    }

    public function test_the_cookie_grants_the_subscribe_topics_as_url_patterns(): void
    {
        $authorization = $this->createAuthorization(
            [DummyPublishableComponent::class => [new Get(uriTemplate: '/dummy_publishable_components/{id}{._format}', class: DummyPublishableComponent::class, name: 'publishable_get', routePrefix: '/component', mercure: true)]],
            ['publishable_get' => '/component/dummy_publishable_components/{id}.{_format}'],
            true,
        );

        $cookie = $authorization->getAuthorizationCookie();

        self::assertSame('__Secure-mercure_access_token', $cookie->getName());
        self::assertTrue($cookie->isSecure());
        $payload = json_decode(base64_decode(strtr(explode('.', (string) $cookie->getValue())[1], '-_', '+/')), true);
        self::assertSame(
            [[
                'type' => 'https://mercure.rocks/authorization-detail',
                'actions' => ['subscribe'],
                'topics' => [
                    ['match' => 'http://example.com/component/dummy_publishable_components/:id\\?', 'match_type' => 'urlpattern'],
                    ['match' => 'http://example.com/component/dummy_publishable_components/:id\\?draft=1', 'match_type' => 'urlpattern'],
                ],
            ]],
            $payload['authorization_details'],
        );
    }

    /**
     * @param array<class-string, list<Get>> $operations
     * @param array<string, string>          $routes
     */
    private function createAuthorization(array $operations, array $routes, bool $draftsGranted = false): MercureAuthorization
    {
        $resourceNames = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $resourceNames->method('create')->willReturn(new ResourceNameCollection(array_keys($operations)));

        $metadata = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadata->method('create')->willReturnCallback(static fn (string $resourceClass): ResourceMetadataCollection => new ResourceMetadataCollection(
            $resourceClass,
            $operations[$resourceClass] ? [new ApiResource(operations: $operations[$resourceClass])] : [],
        ));

        $routeCollection = new RouteCollection();
        foreach ($routes as $name => $path) {
            $routeCollection->add($name, new Route($path));
        }
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        $publishableStatusChecker = $this->createStub(PublishableStatusChecker::class);
        $publishableStatusChecker->method('isGranted')->willReturn($draftsGranted);

        $factory = new DefaultClaimsTokenFactory(
            new LcobucciFactory('ab0a54cf2375ab2368d97166b94e108cab0a54cf2375ab2368d97166b94e108c', protocolVersion: ProtocolVersion::V1),
            ['iss' => 'https://example.com', 'sub' => 'api', 'client_id' => 'api', 'aud' => 'https://example.com/.well-known/mercure'],
        );
        $hub = new MockHub('https://example.com/.well-known/mercure', new StaticTokenProvider('jwt'), static fn (): string => 'id', $factory, protocolVersion: ProtocolVersion::V1);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/login'));

        return new MercureAuthorization(
            $resourceNames,
            $metadata,
            $publishableStatusChecker,
            new RequestContext(host: 'example.com'),
            new Authorization(new HubRegistry($hub)),
            $requestStack,
            $this->createStub(AuthorizationCheckerInterface::class),
            $router,
        );
    }
}
