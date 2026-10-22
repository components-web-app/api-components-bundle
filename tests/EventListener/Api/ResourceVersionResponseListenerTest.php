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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\EventListener\Api\ResourceVersionResponseListener;
use Silverback\ApiComponentsBundle\Exception\ResourceVersionConflictException;
use Silverback\ApiComponentsBundle\Helper\SubRequest\SubRequestKernel;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ResourceVersionResponseListenerTest extends TestCase
{
    private TokenStorage $tokenStorage;

    /** @var list<Request> */
    private array $subRequests = [];

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
    }

    public function test_a_signed_in_response_of_a_versioned_resource_carries_its_version_as_a_strong_etag(): void
    {
        $this->signIn();
        $event = $this->responseEvent(self::component(4), new Response('{}'));

        $this->listener()->onKernelResponse($event);

        self::assertSame('"4"', $event->getResponse()->getEtag());
    }

    public function test_a_response_carrying_the_version_etag_is_never_stored_by_a_shared_cache(): void
    {
        $this->signIn();
        $response = new Response('{}');
        $response->setPublic();
        $response->setSharedMaxAge(3600);
        $event = $this->responseEvent(self::component(4), $response);

        $this->listener()->onKernelResponse($event);

        self::assertSame('no-store, private', $event->getResponse()->headers->get('Cache-Control'));
    }

    public function test_an_anonymous_response_keeps_its_etag(): void
    {
        $response = new Response('{}');
        $response->setEtag('hash');

        $this->listener()->onKernelResponse($this->responseEvent(self::component(4), $response));

        self::assertSame('"hash"', $response->getEtag());
    }

    public function test_a_failed_response_gets_no_version_etag(): void
    {
        $this->signIn();
        $response = new Response('{}', 412);

        $this->listener()->onKernelResponse($this->responseEvent(self::component(4), $response));

        self::assertNull($response->getEtag());
    }

    public function test_an_empty_response_gets_no_version_etag(): void
    {
        $this->signIn();
        $response = new Response('', 204);

        $this->listener()->onKernelResponse($this->responseEvent(self::component(4), $response));

        self::assertNull($response->getEtag());
    }

    public function test_a_response_of_a_resource_that_is_not_versioned_gets_no_version_etag(): void
    {
        $this->signIn();
        $response = new Response('{}');

        $this->listener()->onKernelResponse($this->responseEvent(new \stdClass(), $response));

        self::assertNull($response->getEtag());
    }

    public function test_a_conflict_is_answered_with_the_current_resource_as_the_caller_would_get_it(): void
    {
        $request = Request::create('/component/dummy_components/1?draft=1', 'PATCH', server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_IF_MATCH' => '"1"', 'HTTP_ACCEPT' => 'application/ld+json', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE'], content: '{}');
        $request->cookies->set('jwt', 'token');
        $current = new Response('{"current":true}', 200, ['Content-Type' => 'application/ld+json', 'ETag' => '"2"', 'Cache-Control' => 'public, s-maxage=3600']);
        $event = $this->exceptionEvent($request, new ResourceVersionConflictException());

        $this->listener($current)->onKernelException($event);

        $response = $event->getResponse();
        self::assertSame($current, $response);
        self::assertSame(412, $response->getStatusCode());
        self::assertSame('{"current":true}', $response->getContent());
        self::assertSame('"2"', $response->getEtag());
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
        self::assertCount(1, $this->subRequests);
        $subRequest = $this->subRequests[0];
        self::assertSame('GET', $subRequest->getMethod());
        self::assertSame('/component/dummy_components/1', $subRequest->getPathInfo());
        self::assertSame(['draft' => '1'], $subRequest->query->all());
        self::assertSame('token', $subRequest->cookies->get('jwt'));
        self::assertSame('application/ld+json', $subRequest->headers->get('Accept'));
        self::assertFalse($subRequest->headers->has('If-Match'));
        self::assertFalse($subRequest->headers->has('Content-Type'));
        self::assertFalse($subRequest->headers->has('X-Http-Method-Override'));
    }

    public function test_a_conflict_clears_an_open_manager_and_resets_a_closed_one(): void
    {
        $open = $this->createMock(EntityManagerInterface::class);
        $open->method('isOpen')->willReturn(true);
        $open->expects(self::once())->method('clear');
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $closed->expects(self::never())->method('clear');
        $other = $this->createMock(ObjectManager::class);
        $other->expects(self::once())->method('clear');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn(['open' => $open, 'closed' => $closed, 'other' => $other]);
        $registry->expects(self::once())->method('resetManager')->with('closed');
        $event = $this->exceptionEvent(Request::create('/x', 'PATCH'), new ResourceVersionConflictException());

        $this->listener(new Response(), $registry)->onKernelException($event);

        self::assertSame(412, $event->getResponse()?->getStatusCode());
    }

    public function test_a_conflict_on_a_resource_that_is_gone_is_answered_as_the_read_was(): void
    {
        $notFound = new NotFoundHttpException();
        $event = $this->exceptionEvent(Request::create('/x', 'DELETE'), new ResourceVersionConflictException());

        $this->listener($notFound)->onKernelException($event);

        self::assertNull($event->getResponse());
        self::assertSame($notFound, $event->getThrowable());
    }

    public function test_another_exception_is_left_alone(): void
    {
        $exception = new NotFoundHttpException();
        $event = $this->exceptionEvent(Request::create('/x', 'PATCH'), $exception);

        $this->listener(new Response())->onKernelException($event);

        self::assertNull($event->getResponse());
        self::assertSame($exception, $event->getThrowable());
        self::assertSame([], $this->subRequests);
    }

    public function test_a_conflict_in_a_sub_request_is_left_alone(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent($kernel, Request::create('/x', 'PATCH'), HttpKernelInterface::SUB_REQUEST, new ResourceVersionConflictException());

        $this->listener(new Response())->onKernelException($event);

        self::assertNull($event->getResponse());
        self::assertSame([], $this->subRequests);
    }

    private function signIn(): void
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('admin', null, ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']));
    }

    private static function component(int $version): DummyComponent
    {
        $component = new DummyComponent();
        (new \ReflectionProperty(AbstractComponent::class, 'resourceVersion'))->setValue($component, $version);

        return $component;
    }

    private function responseEvent(object $data, Response $response): ResponseEvent
    {
        $request = Request::create('/x');
        $request->attributes->set('data', $data);

        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }

    private function exceptionEvent(Request $request, \Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $throwable);
    }

    private function listener(Response|\Throwable|null $subResponse = null, ?ManagerRegistry $registry = null): ResourceVersionResponseListener
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(function (Request $request) use ($subResponse): Response {
            $this->subRequests[] = $request;
            if ($subResponse instanceof \Throwable) {
                throw $subResponse;
            }

            return $subResponse ?? new Response();
        });
        if (null === $registry) {
            $registry = $this->createStub(ManagerRegistry::class);
            $registry->method('getManagers')->willReturn([]);
        }

        return new ResourceVersionResponseListener($this->tokenStorage, $registry, new SubRequestKernel($kernel));
    }
}
