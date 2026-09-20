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

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\EventListener\Api\UnpublishedRouteExceptionListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UnpublishedRouteExceptionListenerTest extends TestCase
{
    private UnpublishedRouteExceptionListener $listener;

    protected function setUp(): void
    {
        $this->listener = new UnpublishedRouteExceptionListener();
    }

    public function test_an_access_denied_exception_becomes_not_found(): void
    {
        $event = $this->dispatch(new AccessDeniedException());

        self::assertInstanceOf(NotFoundHttpException::class, $event->getThrowable());
    }

    public function test_an_authentication_exception_becomes_not_found(): void
    {
        $event = $this->dispatch(new AuthenticationException());

        self::assertInstanceOf(NotFoundHttpException::class, $event->getThrowable());
    }

    public function test_a_forbidden_http_exception_becomes_not_found(): void
    {
        $event = $this->dispatch(new AccessDeniedHttpException());

        self::assertInstanceOf(NotFoundHttpException::class, $event->getThrowable());
    }

    public function test_the_rewritten_exception_does_not_carry_the_original_as_previous(): void
    {
        $event = $this->dispatch(new AccessDeniedException());

        self::assertNull($event->getThrowable()->getPrevious());
    }

    public function test_an_unrelated_status_code_is_left_alone(): void
    {
        $original = new UnprocessableEntityHttpException();
        $event = $this->dispatch($original);

        self::assertSame($original, $event->getThrowable());
    }

    public function test_a_request_without_the_attribute_is_left_alone(): void
    {
        $original = new AccessDeniedException();
        $event = $this->dispatch($original, attribute: false);

        self::assertSame($original, $event->getThrowable());
    }

    public function test_a_non_cacheable_method_is_left_alone(): void
    {
        $original = new AccessDeniedException();
        $event = $this->dispatch($original, method: 'POST');

        self::assertSame($original, $event->getThrowable());
    }

    public function test_a_sub_request_is_left_alone(): void
    {
        $original = new AccessDeniedException();
        $event = $this->dispatch($original, requestType: HttpKernelInterface::SUB_REQUEST);

        self::assertSame($original, $event->getThrowable());
    }

    private function dispatch(
        \Throwable $throwable,
        bool $attribute = true,
        string $method = 'GET',
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ExceptionEvent {
        $request = new Request();
        $request->setMethod($method);
        if ($attribute) {
            $request->attributes->set(UnpublishedRouteExceptionListener::REQUEST_ATTRIBUTE, true);
        }

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            $requestType,
            $throwable,
        );

        $this->listener->onKernelException($event);

        return $event;
    }
}
