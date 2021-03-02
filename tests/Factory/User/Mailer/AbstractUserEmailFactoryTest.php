<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Factory\User\Mailer;

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
use Silverback\ApiComponentsBundle\Tests\Functional\Factory\Mailer\User\DummyUserEmailFactory;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

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

    public function test_subscribed_services(): void
    {
        $this->assertEquals(
            [
                RequestStack::class,
                RefererUrlResolver::class,
                Environment::class,
            ],
            AbstractUserEmailFactory::getSubscribedServices()
        );
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
            new class() extends AbstractUser {
            }
        );
    }

    public function test_exception_thrown_if_user_has_no_username(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'email subject');
        $user = new class() extends AbstractUser {
        };
        $user->setEmailAddress('email@address.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The user must have a username set to send them any email');

        $userEmailFactory->create($user, self::VALID_CONTEXT);
    }

    public function test_exception_thrown_if_user_has_no_email_address(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'email subject');
        $user = new class() extends AbstractUser {
        };
        $user->setUsername('my_username');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The user must have an email address set to send them any email');

        $userEmailFactory->create($user, self::VALID_CONTEXT);
    }

    public function test_exception_thrown_if_email_not_rfc_compliant(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'email subject');
        $user = new class() extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('invalid_email:address');

        $this->expectException(RfcComplianceException::class);
        $this->expectExceptionMessageMatches('/[\s\S]/');

        $userEmailFactory->create($user, self::VALID_CONTEXT);
    }

    public function test_exception_thrown_if_no_website_name_context_key(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'email subject');
        $user = new class() extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('email@address.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('You have not specified required context key(s) for the user email factory factory `%s` (expected: `website_name`, `test_key`)', DummyUserEmailFactory::class));

        $userEmailFactory->create($user);
    }

    public function test_create_email_message(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'website name is {{ website_name }}');
        $user = new class() extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('email@address.com');

        $loaderMock = $this->createMock(LoaderInterface::class);
        $twig = new Environment($loaderMock);

        $this->containerInterfaceMock
            ->expects(self::once())
            ->method('get')
            ->with(Environment::class)
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
        $user = new class() extends AbstractUser {
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
            new class() extends AbstractUser {
            }
        );
    }

    public function test_dummy_get_token_url_throws_exception_if_no_default_path_and_no_query_in_current_request(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, null, 'queryKey');

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects(self::once())
            ->method('getMasterRequest')
            ->willReturn(new Request());

        $this->containerInterfaceMock
            ->expects(self::once())
            ->method('get')
            ->with(RequestStack::class)
            ->willReturn($requestStackMock);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(sprintf('The querystring key `%s` could not be found in the request to generate a token URL', 'queryKey'));
        $userEmailFactory->dummyGetTokenUrl(
            new class() extends AbstractUser {
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
            ->method('getMasterRequest')
            ->willReturn($request);

        $refererUrlMock = $this->createMock(RefererUrlResolver::class);
        $refererUrlMock
            ->expects(self::once())
            ->method('getAbsoluteUrl')
            ->with('/query-path')
            ->willReturn('/any-path');

        $this->containerInterfaceMock
            ->expects(self::exactly(2))
            ->method('get')
            ->withConsecutive(
                [RequestStack::class],
                [RefererUrlResolver::class]
            )
            ->willReturnOnConsecutiveCalls(
                $requestStackMock,
                $refererUrlMock
            );

        self::assertEquals(
            '/any-path',
            $userEmailFactory->dummyGetTokenUrl(
                new class() extends AbstractUser {
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
            ->method('getMasterRequest')
            ->willReturn($request);

        $refererUrlMock = $this->createMock(RefererUrlResolver::class);
        $this->containerInterfaceMock
            ->expects(self::exactly(2))
            ->method('get')
            ->withConsecutive([RequestStack::class], [RefererUrlResolver::class])
            ->willReturnOnConsecutiveCalls(
                $requestStackMock,
                $refererUrlMock
            );

        $refererUrlMock
            ->expects(self::once())
            ->method('getAbsoluteUrl')
            ->with('/a-default-path')
            ->willReturn('/any-path');

        self::assertEquals(
            '/any-path',
            $userEmailFactory->dummyGetTokenUrl(
                new class() extends AbstractUser {
                }
            )
        );
    }

    public function test_token_path_variable_populate(): void
    {
        $userEmailFactory = new DummyUserEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/path/{{username}}/{{ token }}');

        $request = new Request();

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects(self::once())
            ->method('getMasterRequest')
            ->willReturn($request);

        $refererUrlMock = $this->createMock(RefererUrlResolver::class);
        $this->containerInterfaceMock
            ->expects(self::exactly(2))
            ->method('get')
            ->withConsecutive([RequestStack::class], [RefererUrlResolver::class])
            ->willReturnOnConsecutiveCalls($requestStackMock, $refererUrlMock);

        $refererUrlMock
            ->expects(self::once())
            ->method('getAbsoluteUrl')
            ->with('/path/token%20username/my_token')
            ->willReturn('/any-path');

        $user = new class() extends AbstractUser {
        };
        $user->setUsername('token username');
        self::assertEquals('/any-path', $userEmailFactory->dummyGetTokenUrl($user));
    }
}
