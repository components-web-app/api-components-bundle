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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Mercure\AnonymousResourceReader;
use Silverback\ApiComponentsBundle\Utility\CallerScopedStateInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Stopwatch\Stopwatch;

class AnonymousResourceReaderTest extends TestCase
{
    private TokenStorage $tokenStorage;
    private UsernamePasswordToken $writerToken;

    /** @var list<array{Request, int, bool, mixed}> */
    private array $handled = [];

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
        $this->writerToken = new UsernamePasswordToken(new InMemoryUser('admin', null, ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);
        $this->tokenStorage->setToken($this->writerToken);
    }

    /**
     * @param iterable<CallerScopedStateInterface> $states
     */
    private function reader(\Closure $respond, iterable $states = [], ?Request $mainRequest = null, ?Stopwatch $stopwatch = null): AnonymousResourceReader
    {
        $requestStack = new RequestStack();
        if ($mainRequest) {
            $requestStack->push($mainRequest);
        }
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturnCallback(function (Request $request, int $type, bool $catch) use ($respond): Response {
            $this->handled[] = [$request, $type, $catch, $this->tokenStorage->getToken()];

            return $respond($request);
        });

        return new AnonymousResourceReader($kernel, $requestStack, $this->tokenStorage, $states, $stopwatch);
    }

    public function test_it_returns_the_body_of_an_anonymous_sub_request_for_the_path(): void
    {
        $body = $this->reader(static fn (): Response => new Response('{"@id":"/_/pages/1"}'))->read('/_/pages/1', 'application/ld+json');

        self::assertSame('{"@id":"/_/pages/1"}', $body);
        [$request, $type, $catch, $token] = $this->handled[0];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/_/pages/1', $request->getPathInfo());
        self::assertSame('application/ld+json', $request->headers->get('Accept'));
        self::assertSame(HttpKernelInterface::SUB_REQUEST, $type);
        self::assertFalse($catch);
        self::assertNull($token);
        self::assertTrue($request->attributes->get(AnonymousResourceReader::REQUEST_ATTRIBUTE));
    }

    public function test_the_writer_token_is_restored_after_the_sub_request(): void
    {
        $this->reader(static fn (): Response => new Response('{}'))->read('/_/pages/1', 'application/ld+json');

        self::assertSame($this->writerToken, $this->tokenStorage->getToken());
    }

    public function test_the_writer_token_is_restored_after_a_failed_sub_request(): void
    {
        try {
            $this->reader(static fn (): never => throw new \LogicException('A bug'))->read('/_/pages/1', 'application/ld+json');
            self::fail('The exception was swallowed.');
        } catch (\LogicException) {
        }

        self::assertSame($this->writerToken, $this->tokenStorage->getToken());
    }

    public function test_no_credential_or_condition_of_the_writer_reaches_the_sub_request(): void
    {
        $main = Request::create('https://admin.example.com/_/pages/1', 'PATCH', [], ['api_components' => 'jwt'], [], [
            'HTTP_AUTHORIZATION' => 'Bearer jwt',
            'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer jwt',
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'secret',
            'PHP_AUTH_DIGEST' => 'digest',
            'AUTH_TYPE' => 'Basic',
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'CONTENT_LENGTH' => '20',
            'HTTP_CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_CONTENT_LENGTH' => '20',
            'HTTP_IF_NONE_MATCH' => '"etag"',
            'HTTP_IF_MATCH' => '"etag"',
            'HTTP_IF_MODIFIED_SINCE' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            'HTTP_IF_UNMODIFIED_SINCE' => 'Sat, 01 Jan 2000 00:00:00 GMT',
            'HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE',
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_PATH' => '/blog',
        ], '{"title":"secret"}');
        $main->headers->set('Cookie', 'api_components=jwt');

        $this->reader(static fn (): Response => new Response('{}'), mainRequest: $main)->read('/_/pages/1', 'application/ld+json');

        $request = $this->handled[0][0];
        self::assertSame([], $request->cookies->all());
        foreach (['Authorization', 'Cookie', 'Content-Type', 'Content-Length', 'If-None-Match', 'If-Match', 'If-Modified-Since', 'If-Unmodified-Since', 'X-Http-Method-Override'] as $header) {
            self::assertFalse($request->headers->has($header), $header);
        }
        foreach (['PHP_AUTH_USER', 'PHP_AUTH_PW', 'PHP_AUTH_DIGEST', 'AUTH_TYPE', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            self::assertFalse($request->server->has($key), $key);
        }
        self::assertNull($request->getUser());
        self::assertSame('', $request->getContent());
        self::assertSame('GET', $request->getMethod());
        self::assertSame('application/ld+json', $request->headers->get('Accept'));
        self::assertSame('admin.example.com', $request->getHost());
        self::assertSame('/blog', $request->headers->get('path'));
    }

    public function test_caller_scoped_state_is_isolated_for_the_sub_request_and_restored_after(): void
    {
        $events = [];
        $state = new class($events) implements CallerScopedStateInterface {
            public function __construct(private array &$events)
            {
            }

            public function isolate(): \Closure
            {
                $this->events[] = 'isolate';

                return function (): void {
                    $this->events[] = 'restore';
                };
            }
        };

        $this->reader(static function () use (&$events): Response {
            $events[] = 'request';

            return new Response('{}');
        }, [$state])->read('/_/pages/1', 'application/ld+json');

        self::assertSame(['isolate', 'request', 'restore'], $events);
    }

    public function test_caller_scoped_state_is_restored_in_reverse_order_after_a_denied_sub_request(): void
    {
        $events = [];
        $states = [];
        foreach (['first', 'second'] as $name) {
            $states[] = new class($events, $name) implements CallerScopedStateInterface {
                public function __construct(private array &$events, private string $name)
                {
                }

                public function isolate(): \Closure
                {
                    return function (): void {
                        $this->events[] = $this->name;
                    };
                }
            };
        }

        $this->reader(static fn (): never => throw new NotFoundHttpException(), $states)->read('/_/pages/1', 'application/ld+json');

        self::assertSame(['second', 'first'], $events);
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function denials(): iterable
    {
        yield 'access denied' => [new AccessDeniedException()];
        yield 'not authenticated' => [new AuthenticationException()];
        yield '401' => [new UnauthorizedHttpException('Bearer')];
        yield '403' => [new AccessDeniedHttpException()];
        yield '404' => [new NotFoundHttpException()];
    }

    #[DataProvider('denials')]
    public function test_a_denied_anonymous_read_returns_null(\Throwable $denial): void
    {
        self::assertNull($this->reader(static fn (): never => throw $denial)->read('/_/pages/1', 'application/ld+json'));
        self::assertSame($this->writerToken, $this->tokenStorage->getToken());
    }

    public function test_an_http_failure_that_is_not_a_denial_is_thrown(): void
    {
        $this->expectException(HttpException::class);

        $this->reader(static fn (): never => throw new HttpException(500))->read('/_/pages/1', 'application/ld+json');
    }

    public function test_a_response_that_is_not_a_200_is_not_a_payload(): void
    {
        self::assertNull($this->reader(static fn (): Response => new Response('', 204))->read('/_/pages/1', 'application/ld+json'));
        self::assertNull($this->reader(static fn (): Response => new Response('moved', 301))->read('/_/pages/1', 'application/ld+json'));
    }

    #[DataProvider('denials')]
    public function test_the_stopwatch_section_a_denied_sub_request_opened_is_closed(\Throwable $denial): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection();
        $stopwatch->start('api_platform.processor.write');

        $this->reader(static function (Request $request) use ($stopwatch, $denial): never {
            $request->attributes->set('_stopwatch_token', 'sub');
            $stopwatch->openSection();

            throw $denial;
        }, stopwatch: $stopwatch)->read('/_/pages/1', 'application/ld+json');

        self::assertTrue($stopwatch->isStarted('api_platform.processor.write'));
        $stopwatch->stop('api_platform.processor.write');
        $stopwatch->stopSection('main');
        self::assertArrayHasKey('sub', $stopwatch->getSections());
    }

    public function test_a_denial_without_a_stopwatch_section_is_still_null(): void
    {
        self::assertNull($this->reader(static fn (): never => throw new NotFoundHttpException(), stopwatch: new Stopwatch())->read('/_/pages/1', 'application/ld+json'));
    }

    public function test_the_stopwatch_section_of_a_failure_that_is_not_a_denial_is_closed_before_it_is_thrown(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->openSection();
        $stopwatch->start('outer');

        try {
            $this->reader(static function (Request $request) use ($stopwatch): never {
                $request->attributes->set('_stopwatch_token', 'sub');
                $stopwatch->openSection();

                throw new HttpException(500);
            }, stopwatch: $stopwatch)->read('/_/pages/1', 'application/ld+json');
            self::fail('The exception was swallowed.');
        } catch (HttpException) {
        }

        self::assertTrue($stopwatch->isStarted('outer'));
    }

    public function test_the_service_definition(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services_doctrine_orm_mercure_publisher.php');

        $definition = $container->getDefinition('silverback.api_components.mercure.anonymous_resource_reader');
        $arguments = $definition->getArguments();

        self::assertSame(AnonymousResourceReader::class, $definition->getClass());
        self::assertSame('http_kernel', (string) $arguments[0]);
        self::assertSame('request_stack', (string) $arguments[1]);
        self::assertSame('security.token_storage', (string) $arguments[2]);
        self::assertInstanceOf(TaggedIteratorArgument::class, $arguments[3]);
        self::assertSame('silverback.api_components.caller_scoped_state', $arguments[3]->getTag());
        self::assertSame('debug.stopwatch', (string) $arguments[4]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $arguments[4]->getInvalidBehavior());

        $publisherArguments = $container->getDefinition('silverback.api_components.mercure.resource_publisher')->getArguments();
        self::assertSame('silverback.api_components.mercure.anonymous_resource_reader', (string) $publisherArguments[5]);
        self::assertSame('doctrine', (string) $publisherArguments[7]);
    }

    public function test_the_services_holding_caller_dependent_memos_are_isolated(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');

        $tagged = array_keys($container->findTaggedServiceIds('silverback.api_components.caller_scoped_state'));
        sort($tagged);

        self::assertSame([
            'silverback.api_components.helper.route.reachability_resolver',
            'silverback.api_components.serializer.resource_metadata_provider',
        ], $tagged);
    }
}
