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
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\Realtime\ApiResource\RealtimeEvent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Cookie;
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
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

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

    public function test_an_admin_is_granted_the_admin_topic_of_a_resource_class_that_is_not_publishable(): void
    {
        $authorization = $this->createAuthorization(
            [Layout::class => [new Get(uriTemplate: '/layouts/{id}', class: Layout::class, name: 'layout_get', mercure: true)]],
            ['layout_get' => '/layouts/{id}'],
            true,
        );

        self::assertSame(['http://example.com/layouts/:id\\?', 'http://example.com/layouts/:id\\?draft=1'], $authorization->getSubscribeTopics());
    }

    public function test_a_caller_who_is_not_an_admin_is_not_granted_the_admin_topic(): void
    {
        $authorization = $this->createAuthorization(
            [Layout::class => [new Get(uriTemplate: '/layouts/{id}', class: Layout::class, name: 'layout_get', mercure: true)]],
            ['layout_get' => '/layouts/{id}'],
        );

        self::assertSame(['http://example.com/layouts/:id\\?'], $authorization->getSubscribeTopics());
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
        $payload = json_decode(base64_decode(strtr(explode('.', (string) $cookie->getValue())[1], '-_', '+/'), true), true);
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

    public function test_the_realtime_topic_is_the_absolute_url_of_the_realtime_operations_route(): void
    {
        $authorization = $this->createAuthorization(
            [RealtimeEvent::class => [self::realtimeOperation()]],
            [RealtimeEvent::OPERATION_NAME => '/_/realtime.{_format}'],
        );

        self::assertSame('http://example.com/_/realtime', $authorization->getRealtimeTopic());
    }

    public function test_the_realtime_topic_includes_the_prefix_the_application_imports_api_platform_routes_under(): void
    {
        $authorization = $this->createAuthorization(
            [RealtimeEvent::class => [self::realtimeOperation()]],
            [RealtimeEvent::OPERATION_NAME => '/_api/_/realtime.{_format}'],
        );

        self::assertSame('http://example.com/_api/_/realtime', $authorization->getRealtimeTopic());
    }

    public function test_a_caller_granted_the_realtime_operations_security_is_granted_the_realtime_topic_after_every_resource_topic(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::once())->method('isGranted')->with(new Expression(RealtimeEvent::SECURITY))->willReturn(true);
        $authorization = $this->createAuthorization(
            [
                Layout::class => [new Get(uriTemplate: '/layouts/{id}', class: Layout::class, name: 'layout_get', mercure: true)],
                RealtimeEvent::class => [self::realtimeOperation()],
            ],
            ['layout_get' => '/layouts/{id}', RealtimeEvent::OPERATION_NAME => '/_/realtime.{_format}'],
            authorizationChecker: $checker,
        );

        self::assertSame(['http://example.com/layouts/:id\\?', 'http://example.com/_/realtime\\?'], $authorization->getSubscribeTopics());
    }

    public function test_a_caller_not_granted_the_realtime_operations_security_is_not_granted_the_realtime_topic(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::once())->method('isGranted')->with(new Expression(RealtimeEvent::SECURITY))->willReturn(false);
        $authorization = $this->createAuthorization(
            [RealtimeEvent::class => [self::realtimeOperation()]],
            [RealtimeEvent::OPERATION_NAME => '/_/realtime.{_format}'],
            authorizationChecker: $checker,
        );

        self::assertSame([], $authorization->getSubscribeTopics());
    }

    public function test_a_caller_with_no_token_is_not_granted_the_realtime_topic(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willThrowException(new AuthenticationCredentialsNotFoundException());
        $authorization = $this->createAuthorization(
            [RealtimeEvent::class => [self::realtimeOperation()]],
            [RealtimeEvent::OPERATION_NAME => '/_/realtime.{_format}'],
            authorizationChecker: $checker,
        );

        self::assertSame([], $authorization->getSubscribeTopics());
    }

    public function test_the_realtime_topic_is_granted_only_on_its_own_security_never_on_an_error_evaluating_it(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willThrowException(new \RuntimeException('broken'));
        $authorization = $this->createAuthorization(
            [RealtimeEvent::class => [self::realtimeOperation()]],
            [RealtimeEvent::OPERATION_NAME => '/_/realtime.{_format}'],
            authorizationChecker: $checker,
        );

        $this->expectException(\RuntimeException::class);
        $authorization->getSubscribeTopics();
    }

    public function test_with_the_realtime_channel_switched_off_no_caller_is_granted_its_topic_or_asked_about_it(): void
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::never())->method('isGranted');
        $metadata = [Layout::class => [new Get(uriTemplate: '/layouts/{id}', class: Layout::class, name: 'layout_get', mercure: true)]];
        $authorization = $this->createAuthorization($metadata, ['layout_get' => '/layouts/{id}'], authorizationChecker: $checker, realtimeEnabled: false);

        self::assertSame(['http://example.com/layouts/:id\\?'], $authorization->getSubscribeTopics());
    }

    public function test_the_cookie_grants_the_realtime_topic_as_a_url_pattern_with_no_query(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);
        $authorization = $this->createAuthorization(
            [RealtimeEvent::class => [self::realtimeOperation()]],
            [RealtimeEvent::OPERATION_NAME => '/_/realtime.{_format}'],
            authorizationChecker: $checker,
        );

        $payload = json_decode(base64_decode(strtr(explode('.', (string) $authorization->getAuthorizationCookie()->getValue())[1], '-_', '+/'), true), true);

        self::assertSame([['match' => 'http://example.com/_/realtime\\?', 'match_type' => 'urlpattern']], $payload['authorization_details'][0]['topics']);
    }

    public function test_the_cookie_and_its_token_expire_after_the_refresh_token_ttl(): void
    {
        $authorization = $this->createAuthorization(
            [Layout::class => [new Get(uriTemplate: '/layouts/{id}', class: Layout::class, name: 'layout_get', mercure: true)]],
            ['layout_get' => '/layouts/{id}'],
            cookieLifetime: 86400,
        );

        $before = time();
        $cookie = $authorization->getAuthorizationCookie();
        $after = time();

        $payload = json_decode(base64_decode(strtr(explode('.', (string) $cookie->getValue())[1], '-_', '+/'), true), true);
        $tokenExpiry = (int) $payload['exp'];
        self::assertGreaterThanOrEqual($before + 86400, $tokenExpiry);
        self::assertLessThanOrEqual($after + 86400, $tokenExpiry);
        self::assertGreaterThanOrEqual($before + 86400, $cookie->getExpiresTime());
        self::assertLessThanOrEqual($after + 86400, $cookie->getExpiresTime());
        self::assertLessThanOrEqual(1, abs($cookie->getExpiresTime() - $tokenExpiry));
    }

    public function test_the_cookie_lifetime_follows_the_configured_ttl(): void
    {
        $authorization = $this->createAuthorization(
            [Layout::class => [new Get(uriTemplate: '/layouts/{id}', class: Layout::class, name: 'layout_get', mercure: true)]],
            ['layout_get' => '/layouts/{id}'],
            cookieLifetime: 60,
        );

        $cookie = $authorization->getAuthorizationCookie();

        self::assertLessThanOrEqual(time() + 60, $cookie->getExpiresTime());
        self::assertGreaterThanOrEqual(time() + 58, $cookie->getExpiresTime());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    public function test_the_clear_cookie_expires_the_authorization_cookie_and_grants_nothing(): void
    {
        $authorization = $this->createAuthorization(
            [DummyPublishableComponent::class => [new Get(uriTemplate: '/dummy_publishable_components/{id}{._format}', class: DummyPublishableComponent::class, name: 'publishable_get', routePrefix: '/component', mercure: true)]],
            ['publishable_get' => '/component/dummy_publishable_components/{id}.{_format}'],
            true,
            Cookie::SAMESITE_LAX,
            'mercure.example.com',
        );

        $authorizationCookie = $authorization->getAuthorizationCookie();
        $clearCookie = $authorization->getClearAuthorizationCookie();

        self::assertNull($clearCookie->getValue());
        self::assertTrue($clearCookie->isCleared());
        self::assertSame(0, $clearCookie->getMaxAge());
        self::assertSame($authorizationCookie->getName(), $clearCookie->getName());
        self::assertSame($authorizationCookie->getPath(), $clearCookie->getPath());
        self::assertSame($authorizationCookie->getDomain(), $clearCookie->getDomain());
        self::assertSame($authorizationCookie->isSecure(), $clearCookie->isSecure());
        self::assertSame($authorizationCookie->isHttpOnly(), $clearCookie->isHttpOnly());
        self::assertSame($authorizationCookie->isPartitioned(), $clearCookie->isPartitioned());
        self::assertSame(Cookie::SAMESITE_LAX, $clearCookie->getSameSite());
        self::assertSame('/.well-known/mercure', $clearCookie->getPath());
        self::assertSame('example.com', $clearCookie->getDomain());
        self::assertTrue($clearCookie->isSecure());
    }

    public function test_the_clear_cookie_does_not_read_the_security_context(): void
    {
        $publishableStatusChecker = $this->createMock(PublishableStatusChecker::class);
        $publishableStatusChecker->expects(self::never())->method('isGranted');
        $resourceNames = $this->createMock(ResourceNameCollectionFactoryInterface::class);
        $resourceNames->expects(self::never())->method('create');

        $authorization = $this->createAuthorization([], [], true, resourceNames: $resourceNames, publishableStatusChecker: $publishableStatusChecker);

        self::assertTrue($authorization->getClearAuthorizationCookie()->isCleared());
    }

    private static function realtimeOperation(): Post
    {
        return new Post(uriTemplate: '/realtime', class: RealtimeEvent::class, name: RealtimeEvent::OPERATION_NAME, routePrefix: '/_', security: RealtimeEvent::SECURITY);
    }

    /**
     * @param array<class-string, list<Operation>> $operations
     * @param array<string, string>                $routes
     */
    private function createAuthorization(
        array $operations,
        array $routes,
        bool $draftsGranted = false,
        string $cookieSameSite = Cookie::SAMESITE_STRICT,
        string $hubHost = 'example.com',
        ?ResourceNameCollectionFactoryInterface $resourceNames = null,
        ?PublishableStatusChecker $publishableStatusChecker = null,
        int $cookieLifetime = 604800,
        ?AuthorizationCheckerInterface $authorizationChecker = null,
        ?bool $realtimeEnabled = null,
    ): MercureAuthorization {
        if (null === $resourceNames) {
            $resourceNames = $this->createStub(ResourceNameCollectionFactoryInterface::class);
            $resourceNames->method('create')->willReturn(new ResourceNameCollection(array_keys($operations)));
        }

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

        if (null === $publishableStatusChecker) {
            $publishableStatusChecker = $this->createStub(PublishableStatusChecker::class);
            $publishableStatusChecker->method('isGranted')->willReturn($draftsGranted);
        }

        $factory = new DefaultClaimsTokenFactory(
            new LcobucciFactory('ab0a54cf2375ab2368d97166b94e108cab0a54cf2375ab2368d97166b94e108c', protocolVersion: ProtocolVersion::V1),
            ['iss' => 'https://example.com', 'sub' => 'api', 'client_id' => 'api', 'aud' => 'https://example.com/.well-known/mercure'],
        );
        $hub = new MockHub('https://' . $hubHost . '/.well-known/mercure', new StaticTokenProvider('jwt'), static fn (): string => 'id', $factory, protocolVersion: ProtocolVersion::V1);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.com/login'));

        return new MercureAuthorization(
            $resourceNames,
            $metadata,
            $publishableStatusChecker,
            new RequestContext(host: 'example.com'),
            new Authorization(new HubRegistry($hub)),
            $requestStack,
            $authorizationChecker ?? $this->createStub(AuthorizationCheckerInterface::class),
            $router,
            $cookieLifetime,
            $cookieSameSite,
            null,
            false,
            ...(null === $realtimeEnabled ? [] : [$realtimeEnabled]),
        );
    }
}
