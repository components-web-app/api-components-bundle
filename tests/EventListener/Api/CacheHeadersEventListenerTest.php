<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Api;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\EventListener\Api\CacheHeadersEventListener;
use Silverback\ApiComponentsBundle\EventListener\Api\UnpublishedRouteExceptionListener;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class CacheHeadersEventListenerTest extends TestCase
{
    private TokenStorageInterface $tokenStorage;
    private ?\DateTimeImmutable $nextLiveAt = null;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createStub(TokenStorageInterface::class);
        $this->nextLiveAt = null;
    }

    public function test_authenticated_request_for_configured_resource_is_marked_private_no_store(): void
    {
        $this->authenticateAsUser();
        $response = $this->dispatch(
            resourceClass: CacheHeadersConfiguredResource::class,
            method: Request::METHOD_GET,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
            initialSharedMaxAge: 3600,
        );

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->getCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_authenticated_request_for_subclass_of_configured_resource_is_marked_private(): void
    {
        $this->authenticateAsUser();
        $response = $this->dispatch(
            resourceClass: CacheHeadersConfiguredChildResource::class,
            method: Request::METHOD_GET,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
        );

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->getCacheControlDirective('no-store'));
    }

    public function test_authenticated_request_for_publishable_resource_is_marked_private(): void
    {
        $this->authenticateAsUser();
        $response = $this->dispatch(
            resourceClass: CacheHeadersPublishableResource::class,
            method: Request::METHOD_GET,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
        );

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->getCacheControlDirective('no-store'));
    }

    public function test_authenticated_request_for_unaffected_resource_keeps_public_cache(): void
    {
        $this->authenticateAsUser();
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: Request::METHOD_GET,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
            initialSharedMaxAge: 3600,
        );

        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_non_cacheable_method_is_left_untouched_even_for_configured_resource(): void
    {
        $this->authenticateAsUser();
        $response = $this->dispatch(
            resourceClass: CacheHeadersConfiguredResource::class,
            method: Request::METHOD_POST,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
            initialSharedMaxAge: 3600,
        );

        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_missing_resource_class_is_left_untouched(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::never())->method('getToken');
        $this->tokenStorage = $tokenStorage;
        $response = $this->dispatch(
            resourceClass: null,
            method: Request::METHOD_GET,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
            initialSharedMaxAge: 3600,
        );

        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_non_string_resource_class_is_left_untouched(): void
    {
        $response = $this->dispatch(
            resourceClass: ['not', 'a', 'string'],
            method: Request::METHOD_GET,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
            initialSharedMaxAge: 3600,
        );

        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_anonymous_request_for_configured_resource_keeps_public_cache(): void
    {
        $this->tokenStorage->method('getToken')->willReturn(null);
        $response = $this->dispatch(
            resourceClass: CacheHeadersConfiguredResource::class,
            method: Request::METHOD_GET,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
            initialSharedMaxAge: 3600,
        );

        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_token_without_user_interface_keeps_public_cache(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $this->tokenStorage->method('getToken')->willReturn($token);

        $response = $this->dispatch(
            resourceClass: CacheHeadersConfiguredResource::class,
            method: Request::METHOD_GET,
            personalisedResourceClasses: [CacheHeadersConfiguredResource::class],
            initialSharedMaxAge: 3600,
        );

        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('s-maxage'));
    }

    private function authenticateAsUser(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($this->createStub(UserInterface::class));
        $this->tokenStorage->method('getToken')->willReturn($token);
    }

    public function test_a_response_for_a_route_which_is_not_live_is_never_stored(): void
    {
        $response = $this->dispatch(Route::class, 'GET', [], 3600, true);

        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_an_anonymous_route_response_is_capped_at_the_next_go_live_moment(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('+60 seconds');

        $response = $this->dispatch(Route::class, 'GET', [], 3600);

        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_an_anonymous_route_response_is_not_extended_when_the_next_go_live_moment_is_further_away(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('+7200 seconds');

        $response = $this->dispatch(Route::class, 'GET', [], 3600);

        self::assertSame(3600, (int) $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function test_an_anonymous_route_response_is_left_untouched_when_nothing_is_scheduled(): void
    {
        $this->nextLiveAt = null;

        $response = $this->dispatch(Route::class, 'GET', [], 3600);

        self::assertSame(3600, (int) $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function test_an_unrelated_resource_response_is_not_capped(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('+60 seconds');

        $response = $this->dispatch(CacheHeadersUnaffectedResource::class, 'GET', [], 3600);

        self::assertSame(3600, (int) $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function test_a_resource_listed_for_scheduled_expiry_by_interface_is_capped(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('+60 seconds');

        $response = $this->dispatch(
            resourceClass: CacheHeadersConfiguredChildResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            scheduledExpiryResourceClasses: [CacheHeadersConfiguredResource::class],
        );

        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_a_pending_expires_caps_a_resource_which_is_not_listed_for_scheduled_expiry(): void
    {
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            expires: (new \DateTimeImmutable())->modify('+60 seconds'),
        );

        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_a_past_expires_does_not_cap(): void
    {
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            expires: (new \DateTimeImmutable())->modify('-60 seconds'),
        );

        self::assertSame(3600, (int) $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function test_the_soonest_of_a_pending_expires_and_the_next_go_live_moment_wins(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('+1800 seconds');

        $response = $this->dispatch(
            resourceClass: Route::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            expires: (new \DateTimeImmutable())->modify('+60 seconds'),
        );

        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_the_next_go_live_moment_wins_when_it_precedes_a_pending_expires(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('+60 seconds');

        $response = $this->dispatch(
            resourceClass: Route::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            expires: (new \DateTimeImmutable())->modify('+1800 seconds'),
        );

        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_max_age_is_capped_alongside_shared_max_age(): void
    {
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            expires: (new \DateTimeImmutable())->modify('+60 seconds'),
            initialMaxAge: 3600,
        );

        self::assertDirectiveCappedAtAMinute($response, 'max-age');
        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_max_age_is_capped_when_there_is_no_shared_max_age(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('+60 seconds');

        $response = $this->dispatch(
            resourceClass: Route::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialMaxAge: 3600,
        );

        self::assertDirectiveCappedAtAMinute($response, 'max-age');
        self::assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_a_max_age_already_below_the_next_transition_is_kept(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('+60 seconds');

        $response = $this->dispatch(
            resourceClass: Route::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            initialMaxAge: 30,
        );

        self::assertSame('30', $response->headers->getCacheControlDirective('max-age'));
        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_a_transition_which_has_just_passed_caps_at_zero(): void
    {
        $this->nextLiveAt = (new \DateTimeImmutable())->modify('-1 seconds');

        $response = $this->dispatch(Route::class, 'GET', [], 3600);

        self::assertSame('0', $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function test_a_response_with_no_cache_directives_is_left_untouched(): void
    {
        $response = $this->dispatch(
            resourceClass: Route::class,
            method: 'GET',
            personalisedResourceClasses: [],
            expires: (new \DateTimeImmutable())->modify('+60 seconds'),
        );

        self::assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->hasCacheControlDirective('max-age'));
    }

    public function test_a_hidden_publication_sets_expires_and_caps_the_shared_cache_lifetime(): void
    {
        $publishedAt = (new \DateTimeImmutable('@' . time()))->modify('+60 seconds');
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            nextHiddenPublication: $publishedAt,
        );

        self::assertSame($publishedAt->getTimestamp(), $response->getExpires()?->getTimestamp());
        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_a_hidden_publication_after_a_pending_expires_keeps_the_expires(): void
    {
        $expires = (new \DateTimeImmutable('@' . time()))->modify('+60 seconds');
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            expires: $expires,
            nextHiddenPublication: $expires->modify('+1800 seconds'),
        );

        self::assertSame($expires->getTimestamp(), $response->getExpires()?->getTimestamp());
    }

    public function test_a_hidden_publication_at_a_pending_expires_keeps_the_expires(): void
    {
        $expires = (new \DateTimeImmutable('@' . time()))->modify('+60 seconds');
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            expires: $expires,
            nextHiddenPublication: $expires,
        );

        self::assertSame($expires->getTimestamp(), $response->getExpires()?->getTimestamp());
    }

    public function test_a_hidden_publication_before_a_pending_expires_replaces_the_expires(): void
    {
        $publishedAt = (new \DateTimeImmutable('@' . time()))->modify('+60 seconds');
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            expires: $publishedAt->modify('+1800 seconds'),
            nextHiddenPublication: $publishedAt,
        );

        self::assertSame($publishedAt->getTimestamp(), $response->getExpires()?->getTimestamp());
        self::assertDirectiveCappedAtAMinute($response, 's-maxage');
    }

    public function test_a_hidden_publication_attribute_which_is_not_a_date_is_ignored(): void
    {
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            nextHiddenPublication: '+60 seconds',
        );

        self::assertNull($response->getExpires());
        self::assertSame(3600, (int) $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function test_a_hidden_publication_does_not_set_expires_on_an_authenticated_response(): void
    {
        $this->authenticateAsUser();
        $response = $this->dispatch(
            resourceClass: CacheHeadersUnaffectedResource::class,
            method: 'GET',
            personalisedResourceClasses: [],
            initialSharedMaxAge: 3600,
            nextHiddenPublication: (new \DateTimeImmutable())->modify('+60 seconds'),
        );

        self::assertNull($response->getExpires());
    }

    private static function assertDirectiveCappedAtAMinute(Response $response, string $directive): void
    {
        $value = $response->headers->getCacheControlDirective($directive);
        self::assertIsString($value);
        self::assertGreaterThanOrEqual(58, (int) $value);
        self::assertLessThanOrEqual(60, (int) $value);
    }

    /**
     * @param array<class-string> $personalisedResourceClasses
     * @param array<class-string> $scheduledExpiryResourceClasses
     */
    private function dispatch(
        mixed $resourceClass,
        string $method,
        array $personalisedResourceClasses,
        ?int $initialSharedMaxAge = null,
        bool $unpublishedRoute = false,
        array $scheduledExpiryResourceClasses = [Route::class],
        ?\DateTimeInterface $expires = null,
        ?int $initialMaxAge = null,
        mixed $nextHiddenPublication = null,
    ): Response {
        $publishableReader = new PublishableAttributeReader($this->createStub(ManagerRegistry::class));
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn($publishableReader);

        $routeRepository = $this->createStub(RouteRepository::class);
        $routeRepository->method('findNextLiveAt')->willReturn($this->nextLiveAt);

        $listener = new CacheHeadersEventListener(
            $this->tokenStorage,
            $statusChecker,
            $routeRepository,
            $personalisedResourceClasses,
            $scheduledExpiryResourceClasses,
        );

        $request = new Request();
        $request->setMethod($method);
        if (null !== $resourceClass) {
            $request->attributes->set('_api_resource_class', $resourceClass);
        }
        if ($unpublishedRoute) {
            $request->attributes->set(UnpublishedRouteExceptionListener::REQUEST_ATTRIBUTE, true);
        }
        if (null !== $nextHiddenPublication) {
            $request->attributes->set(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE, $nextHiddenPublication);
        }

        $response = new Response();
        $response->setPublic();
        if (null !== $initialSharedMaxAge) {
            $response->setSharedMaxAge($initialSharedMaxAge);
        }
        if (null !== $initialMaxAge) {
            $response->setMaxAge($initialMaxAge);
        }
        if (null !== $expires) {
            $response->setExpires($expires);
        }

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $listener->onPostRespond($event);

        return $event->getResponse();
    }
}

#[Publishable]
class CacheHeadersPublishableResource
{
}

class CacheHeadersConfiguredResource
{
}

class CacheHeadersConfiguredChildResource extends CacheHeadersConfiguredResource
{
}

class CacheHeadersUnaffectedResource
{
}
