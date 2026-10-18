<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\SubRequest;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\SubRequest\SubRequestKernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class SubRequestKernelTest extends TestCase
{
    public function test_a_sub_request_that_throws_leaves_the_callers_stopwatch_section_active(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection();
        $stopwatch->start('outer');

        $kernel = new SubRequestKernel($this->kernelOpeningASectionThen(new AccessDeniedHttpException(), $stopwatch), $stopwatch);

        try {
            $kernel->handle(Request::create('/'));
            self::fail('The exception should propagate.');
        } catch (AccessDeniedHttpException) {
        }

        $stopwatch->stop('outer');
        self::assertFalse($stopwatch->isStarted('outer'));
    }

    public function test_nested_sub_requests_that_throw_each_close_their_own_section(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection();
        $stopwatch->start('outer');

        $inner = new SubRequestKernel($this->kernelOpeningASectionThen(new AccessDeniedHttpException(), $stopwatch), $stopwatch);
        $outerKernel = new class($inner, $stopwatch) implements HttpKernelInterface {
            public function __construct(private readonly SubRequestKernel $inner, private readonly Stopwatch $stopwatch)
            {
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                $request->attributes->set('_stopwatch_token', 'outer-sub');
                $this->stopwatch->openSection();
                try {
                    $this->inner->handle(Request::create('/inner'));
                } catch (AccessDeniedHttpException) {
                }

                throw new AccessDeniedHttpException();
            }
        };

        try {
            (new SubRequestKernel($outerKernel, $stopwatch))->handle(Request::create('/'));
            self::fail('The exception should propagate.');
        } catch (AccessDeniedHttpException) {
        }

        $stopwatch->stop('outer');
        self::assertFalse($stopwatch->isStarted('outer'));
    }

    public function test_a_response_is_returned_and_the_kernel_is_asked_for_an_uncaught_sub_request(): void
    {
        $response = new Response('ok');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/');
        $kernel->expects(self::once())->method('handle')->with($request, HttpKernelInterface::SUB_REQUEST, false)->willReturn($response);

        self::assertSame($response, (new SubRequestKernel($kernel, new Stopwatch()))->handle($request));
    }

    public function test_without_a_stopwatch_the_exception_still_propagates(): void
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willThrowException(new AccessDeniedHttpException());

        $this->expectException(AccessDeniedHttpException::class);
        (new SubRequestKernel($kernel))->handle(Request::create('/'));
    }

    public function test_a_sub_request_without_a_section_closes_nothing(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection();
        $stopwatch->start('outer');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willThrowException(new AccessDeniedHttpException());

        try {
            (new SubRequestKernel($kernel, $stopwatch))->handle(Request::create('/'));
        } catch (AccessDeniedHttpException) {
        }

        $stopwatch->stop('outer');
        self::assertFalse($stopwatch->isStarted('outer'));
    }

    public function test_a_sub_request_that_returns_with_its_section_still_open_leaves_the_callers_section_active(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection();
        $stopwatch->start('outer');
        $response = new Response('ok');

        $kernel = new class($stopwatch, $response) implements HttpKernelInterface {
            public function __construct(private readonly Stopwatch $stopwatch, private readonly Response $response)
            {
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                $request->attributes->set('_stopwatch_token', 'sub');
                $this->stopwatch->openSection();

                return $this->response;
            }
        };

        self::assertSame($response, (new SubRequestKernel($kernel, $stopwatch))->handle(Request::create('/')));

        $stopwatch->stop('outer');
        self::assertFalse($stopwatch->isStarted('outer'));
        self::assertArrayHasKey('sub', $stopwatch->getSections());
    }

    public function test_a_sub_request_that_closes_its_own_section_is_not_closed_again(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection();
        $stopwatch->start('outer');

        $kernel = new class($stopwatch) implements HttpKernelInterface {
            public function __construct(private readonly Stopwatch $stopwatch)
            {
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                $request->attributes->set('_stopwatch_token', 'sub');
                $this->stopwatch->openSection();
                $this->stopwatch->stopSection('sub');

                return new Response('ok');
            }
        };

        (new SubRequestKernel($kernel, $stopwatch))->handle(Request::create('/'));

        $stopwatch->stop('outer');
        $stopwatch->stopSection('main');
        self::assertArrayHasKey('main', $stopwatch->getSections());
        self::assertArrayHasKey('sub', $stopwatch->getSections());
        self::assertTrue(false === $stopwatch->getSections()['main']->getEvent('outer')->isStarted());
    }

    public function test_the_sub_request_is_timed_in_the_callers_section(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection();
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('ok'));

        (new SubRequestKernel($kernel, $stopwatch))->handle(Request::create('/'));

        self::assertFalse($stopwatch->isStarted(SubRequestKernel::STOPWATCH_EVENT));
        $stopwatch->stopSection('main');
        self::assertCount(1, $stopwatch->getSections()['main']->getEvent(SubRequestKernel::STOPWATCH_EVENT)->getPeriods());
    }

    public function test_a_section_that_cannot_be_closed_is_left_alone(): void
    {
        $stopwatch = new Stopwatch();
        $response = new Response('ok');
        $kernel = new class($stopwatch, $response) implements HttpKernelInterface {
            public function __construct(private readonly Stopwatch $stopwatch, private readonly Response $response)
            {
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                $request->attributes->set('_stopwatch_token', 'sub');
                $this->stopwatch->stop(SubRequestKernel::STOPWATCH_EVENT);

                return $this->response;
            }
        };

        self::assertSame($response, (new SubRequestKernel($kernel, $stopwatch))->handle(Request::create('/')));
        self::assertArrayNotHasKey('sub', $stopwatch->getSections());
    }

    public function test_the_service_definition_and_its_users(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config')))->load('services.php');

        $definition = $container->getDefinition('silverback.api_components.helper.sub_request_kernel');
        $arguments = $definition->getArguments();
        self::assertSame(SubRequestKernel::class, $definition->getClass());
        self::assertSame('http_kernel', (string) $arguments[0]);
        self::assertSame('debug.stopwatch', (string) $arguments[1]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $arguments[1]->getInvalidBehavior());
        self::assertSame('silverback.api_components.helper.sub_request_kernel', (string) $container->getAlias(SubRequestKernel::class));
        self::assertSame(SubRequestKernel::class, (string) $container->getDefinition('silverback.security.voter.component_voter')->getArgument(2));
    }

    private function kernelOpeningASectionThen(\Throwable $exception, Stopwatch $stopwatch): HttpKernelInterface
    {
        return new class($exception, $stopwatch) implements HttpKernelInterface {
            public function __construct(private readonly \Throwable $exception, private readonly Stopwatch $stopwatch)
            {
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                $request->attributes->set('_stopwatch_token', bin2hex(random_bytes(3)));
                $this->stopwatch->openSection();

                throw $this->exception;
            }
        };
    }
}
