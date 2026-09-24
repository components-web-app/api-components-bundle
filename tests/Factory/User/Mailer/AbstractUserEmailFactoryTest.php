<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Factory\User\Mailer;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Event\UserEmailMessageEvent;
use Silverback\ApiComponentsBundle\Exception\BadMethodCallException;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\RfcComplianceException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\AbstractUserEmailFactory;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Helper\RelativeUrlPath;
use Silverback\ApiComponentsBundle\Tests\Functional\Factory\Mailer\User\DummyUserEmailFactory;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

#[AllowMockObjectsWithoutExpectations]
class AbstractUserEmailFactoryTest extends TestEmailCase
{
    private const VALID_CONTEXT = ['website_name' => 'my website', 'test_key' => 'any value'];
    /**
     * @var MockObject|ContainerInterface
     */
    private MockObject $containerInterfaceMock;
    /**
     * @var MockObject|EventDispatcherInterface
     */
    private MockObject $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->containerInterfaceMock = $this->createMock(ContainerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
    }

    public function test_create_email_called_before_init_user_throws_exception(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('You must call the method `initUser` before `createEmailMessage`');
        $factory = new class($this->containerInterfaceMock, $this->eventDispatcherMock, '-') extends AbstractUserEmailFactory {
            public function create(AbstractUser $user, array $context = []): ?RawMessage
            {
                return $this->createEmailMessage($context);
            }

            protected function getTemplate(): string
            {
                return '';
            }
        };
        $factory->create(
            new class extends AbstractUser {
            }
        );
    }

    public function test_exception_thrown_if_user_has_no_username(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'email subject');
        $user = new class extends AbstractUser {
        };
        $user->setEmailAddress('email@address.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The user must have a username set to send them any email');

        $userEmailFactory->create($user, self::VALID_CONTEXT);
    }

    public function test_exception_thrown_if_user_has_no_email_address(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'email subject');
        $user = new class extends AbstractUser {
        };
        $user->setUsername('my_username');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The user must have an email address set to send them any email');

        $userEmailFactory->create($user, self::VALID_CONTEXT);
    }

    public function test_exception_thrown_if_email_not_rfc_compliant(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'email subject');
        $user = new class extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('invalid_email:address');

        $this->expectException(RfcComplianceException::class);
        $this->expectExceptionMessageMatches('/[\s\S]/');

        $userEmailFactory->create($user, self::VALID_CONTEXT);
    }

    public function test_exception_thrown_if_no_website_name_context_key(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'email subject');
        $user = new class extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('email@address.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('You have not specified required context key(s) for the user email factory factory `%s` (expected: `website_name`, `test_key`)', DummyUserEmailFactory::class));

        $userEmailFactory->create($user);
    }

    public function test_create_email_message(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'website name is {{ website_name }}');
        $user = new class extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('email@address.com');

        $loaderMock = $this->createMock(LoaderInterface::class);
        $twig = new Environment($loaderMock);

        $this->containerInterfaceMock
            ->expects(self::once())
            ->method('get')
            ->with('twig')
            ->willReturn($twig);

        $emailMessage = (new TemplatedEmail())
            ->to(Address::create('email@address.com'))
            ->subject('website name is my website')
            ->htmlTemplate('@SilverbackApiComponents/emails/template.html.twig')
            ->context(
                array_merge(
                    self::VALID_CONTEXT,
                    [
                        'user' => $user,
                    ]
                )
            );

        $event = new UserEmailMessageEvent(DummyUserEmailFactory::class, $emailMessage);
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($event);

        $returnedEmailMessage = $userEmailFactory->create($user, self::VALID_CONTEXT);

        $this->assertEmailEquals($emailMessage, $returnedEmailMessage, AbstractUserEmailFactory::MESSAGE_ID_PREFIX);
    }

    public function test_do_not_create_email_message_if_not_enabled(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', false);
        $user = new class extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('email@address.com');

        $this->containerInterfaceMock
            ->expects(self::never())
            ->method('get');

        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        $returnedEmailMessage = $userEmailFactory->create($user, self::VALID_CONTEXT);
        $this->assertNull($returnedEmailMessage);
    }

    public function test_dummy_get_token_url_throws_exception_if_no_paths(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The `defaultRedirectPath` or `redirectPathQueryKey` must be set');
        $userEmailFactory->dummyGetTokenUrl(
            new class extends AbstractUser {
            }
        );
    }

    public function test_dummy_get_token_url_throws_exception_if_no_default_path_and_no_query_in_current_request(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, null, 'queryKey');

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn(new Request());

        $this->containerInterfaceMock
            ->expects(self::once())
            ->method('get')
            ->with(RequestStack::class)
            ->willReturn($requestStackMock);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(\sprintf('The querystring key `%s` could not be found in the request to generate a token URL', 'queryKey'));
        $userEmailFactory->dummyGetTokenUrl(
            new class extends AbstractUser {
            }
        );
    }

    public function test_dummy_get_token_url_can_get_path_from_querystring_over_default_path(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/a-default-path', 'queryKey');

        $request = new Request();
        $request->query->set('queryKey', '/query-path');

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $refererUrlMock = $this->createMock(RefererUrlResolver::class);
        $refererUrlMock
            ->expects(self::once())
            ->method('getAbsoluteUrl')
            ->with(RelativeUrlPath::fromConfiguration('/query-path'))
            ->willReturn('/any-path');

        $invokedCount = self::exactly(2);
        $callParams = [[RequestStack::class], [RefererUrlResolver::class]];
        $willReturn = [$requestStackMock, $refererUrlMock];
        $this->containerInterfaceMock
            ->expects($invokedCount)
            ->method('get')
            ->willReturnCallback(function (...$parameters) use ($invokedCount, $callParams, $willReturn) {
                $currentInvocationCount = $invokedCount->numberOfInvocations();
                $this->assertSame($callParams[$currentInvocationCount - 1], $parameters);

                return $willReturn[$currentInvocationCount - 1];
            });

        self::assertEquals(
            '/any-path',
            $userEmailFactory->dummyGetTokenUrl(
                new class extends AbstractUser {
                }
            )
        );
    }

    public function test_dummy_get_token_url_can_get_path_from_default_path(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/a-default-path', 'queryKey');

        $request = new Request();

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $refererUrlMock = $this->createMock(RefererUrlResolver::class);

        $invokedCount = self::exactly(2);
        $callParams = [[RequestStack::class], [RefererUrlResolver::class]];
        $willReturn = [$requestStackMock, $refererUrlMock];
        $this->containerInterfaceMock
            ->expects($invokedCount)
            ->method('get')
            ->willReturnCallback(function (...$parameters) use ($invokedCount, $callParams, $willReturn) {
                $currentInvocationCount = $invokedCount->numberOfInvocations();
                $this->assertSame($callParams[$currentInvocationCount - 1], $parameters);

                return $willReturn[$currentInvocationCount - 1];
            });

        $refererUrlMock
            ->expects(self::once())
            ->method('getAbsoluteUrl')
            ->with(RelativeUrlPath::fromConfiguration('/a-default-path'))
            ->willReturn('/any-path');

        self::assertEquals(
            '/any-path',
            $userEmailFactory->dummyGetTokenUrl(
                new class extends AbstractUser {
                }
            )
        );
    }

    public function test_null_path_variable_placeholder_is_not_replaced(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/path/{{new_email}}');

        $refererUrlMock = $this->createMock(RefererUrlResolver::class);
        $invokedCount = self::exactly(1);
        $callParams = [[RefererUrlResolver::class]];
        $willReturn = [$refererUrlMock];
        $this->containerInterfaceMock
            ->expects($invokedCount)
            ->method('get')
            ->willReturnCallback(function (...$parameters) use ($invokedCount, $callParams, $willReturn) {
                $currentInvocationCount = $invokedCount->numberOfInvocations();
                $this->assertSame($callParams[$currentInvocationCount - 1], $parameters);

                return $willReturn[$currentInvocationCount - 1];
            });

        $refererUrlMock
            ->expects(self::once())
            ->method('getAbsoluteUrl')
            ->with(RelativeUrlPath::fromConfiguration('/path/{{new_email}}'))
            ->willReturn('/any-path');

        self::assertEquals('/any-path', $userEmailFactory->dummyGetTokenUrl(new class extends AbstractUser {
        }));
    }

    public function test_token_path_variable_populate(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/path/{{username}}/{{ token }}');

        $refererUrlMock = $this->createMock(RefererUrlResolver::class);
        $invokedCount = self::exactly(1);
        $callParams = [[RefererUrlResolver::class]];
        $willReturn = [$refererUrlMock];
        $this->containerInterfaceMock
            ->expects($invokedCount)
            ->method('get')
            ->willReturnCallback(function (...$parameters) use ($invokedCount, $callParams, $willReturn) {
                $currentInvocationCount = $invokedCount->numberOfInvocations();
                $this->assertSame($callParams[$currentInvocationCount - 1], $parameters);

                return $willReturn[$currentInvocationCount - 1];
            });

        $refererUrlMock
            ->expects(self::once())
            ->method('getAbsoluteUrl')
            ->with(RelativeUrlPath::fromConfiguration('/path/token%20username/my_token'))
            ->willReturn('/any-path');

        $user = new class extends AbstractUser {
        };
        $user->setUsername('token username');
        self::assertEquals('/any-path', $userEmailFactory->dummyGetTokenUrl($user));
    }

    private function containerProviding(array $services): void
    {
        $this->containerInterfaceMock
            ->method('get')
            ->willReturnCallback(static fn (string $id) => $services[$id] ?? throw new \LogicException(\sprintf('The service `%s` was not expected to be fetched', $id)));
    }

    private function requestStackWithQuery(array $query): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request($query));

        return $requestStack;
    }

    private function resolverExpecting(string $path): RefererUrlResolver
    {
        $resolver = $this->createMock(RefererUrlResolver::class);
        $resolver
            ->expects(self::once())
            ->method('getAbsoluteUrl')
            ->with(RelativeUrlPath::fromConfiguration($path))
            ->willReturn('https://www.example.com' . $path);

        return $resolver;
    }

    public static function unsafeQueryPaths(): iterable
    {
        yield 'absolute url' => ['https://evil.example/{{ token }}'];
        yield 'protocol relative' => ['//evil.example/{{ token }}'];
        yield 'backslash' => ['/\\evil.example/{{ token }}'];
        yield 'not rooted' => ['evil/{{ token }}'];
        yield 'control character' => ["/\t/evil.example/{{ token }}"];
    }

    #[DataProvider('unsafeQueryPaths')]
    public function test_a_query_path_that_is_not_a_plain_relative_path_falls_back_to_the_default_path(string $queryPath): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/default/{{ token }}', 'queryKey');
        $this->containerProviding([
            RequestStack::class => $this->requestStackWithQuery(['queryKey' => $queryPath]),
            RefererUrlResolver::class => $this->resolverExpecting('/default/my_token'),
        ]);

        self::assertSame('https://www.example.com/default/my_token', $userEmailFactory->dummyGetTokenUrl(new class extends AbstractUser {
        }));
    }

    public function test_a_query_path_that_is_not_a_plain_relative_path_with_no_default_path_is_refused(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, null, 'queryKey');
        $this->containerProviding([
            RequestStack::class => $this->requestStackWithQuery(['queryKey' => 'https://evil.example/{{ token }}']),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $userEmailFactory->dummyGetTokenUrl(new class extends AbstractUser {
        });
    }

    public function test_a_query_path_that_is_not_a_string_falls_back_to_the_default_path(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/default', 'queryKey');
        $this->containerProviding([
            RequestStack::class => $this->requestStackWithQuery(['queryKey' => ['/query-path']]),
            RefererUrlResolver::class => $this->resolverExpecting('/default'),
        ]);

        self::assertSame('https://www.example.com/default', $userEmailFactory->dummyGetTokenUrl(new class extends AbstractUser {
        }));
    }

    public function test_a_query_path_has_its_variables_populated(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/default', 'queryKey');
        $this->containerProviding([
            RequestStack::class => $this->requestStackWithQuery(['queryKey' => '/query/{{ username }}/{{ token }}']),
            RefererUrlResolver::class => $this->resolverExpecting('/query/a%2F%2Fb/my_token'),
        ]);

        $user = new class extends AbstractUser {
        };
        $user->setUsername('a//b');

        self::assertSame('https://www.example.com/query/a%2F%2Fb/my_token', $userEmailFactory->dummyGetTokenUrl($user));
    }

    public function test_no_main_request_uses_the_default_path(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/default', 'queryKey');
        $this->containerProviding([
            RequestStack::class => new RequestStack(),
            RefererUrlResolver::class => $this->resolverExpecting('/default'),
        ]);

        self::assertSame('https://www.example.com/default', $userEmailFactory->dummyGetTokenUrl(new class extends AbstractUser {
        }));
    }

    public function test_the_request_is_not_read_when_no_query_key_is_configured(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/default');
        $this->containerProviding([
            RefererUrlResolver::class => $this->resolverExpecting('/default'),
        ]);

        self::assertSame('https://www.example.com/default', $userEmailFactory->dummyGetTokenUrl(new class extends AbstractUser {
        }));
    }

    public static function configuredAbsoluteUrls(): iterable
    {
        yield 'absolute url' => ['https://app.example.com/verify/{{ token }}', 'https://app.example.com/verify/my_token'];
        yield 'protocol relative' => ['//app.example.com/verify/{{ token }}', '//app.example.com/verify/my_token'];
    }

    #[DataProvider('configuredAbsoluteUrls')]
    public function test_a_configured_absolute_default_path_is_used_as_it_is(string $defaultPath, string $expected): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, $defaultPath, 'queryKey');
        $this->containerProviding([
            RequestStack::class => $this->requestStackWithQuery([]),
        ]);

        self::assertSame($expected, $userEmailFactory->dummyGetTokenUrl(new class extends AbstractUser {
        }));
    }
}
