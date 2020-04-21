<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Factory\Mailer\User;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Event\UserEmailMessageEvent;
use Silverback\ApiComponentBundle\Exception\BadMethodCallException;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Exception\RfcComplianceException;
use Silverback\ApiComponentBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentBundle\Factory\Mailer\User\AbstractUserEmailFactory;
use Silverback\ApiComponentBundle\Tests\Functional\Factory\Mailer\User\DummyUserEmailFactory;
use Silverback\ApiComponentBundle\Url\RefererUrlHelper;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

class AbstractUserEmailFactoryTest extends TestCase
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
        $this->assertEquals([
            RequestStack::class,
            RefererUrlHelper::class,
            Environment::class,
        ], AbstractUserEmailFactory::getSubscribedServices());
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
        $factory->create(new class() extends AbstractUser {
        });
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
        $userEmailFactory = new DummyUserEmailFactory(
            $this->containerInterfaceMock,
            $this->eventDispatcherMock,
            'website name is {{ website_name }}'
        );
        $user = new class() extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('email@address.com');

        $loaderMock = $this->createMock(LoaderInterface::class);
        $twig = new Environment($loaderMock);

        $this->containerInterfaceMock
            ->expects($this->once())
            ->method('get')
            ->with(Environment::class)
            ->willReturn($twig);

        $emailMessage = (new TemplatedEmail())
            ->to(Address::fromString('email@address.com'))
            ->subject('website name is my website')
            ->htmlTemplate('@SilverbackApiComponent/emails/template.html.twig')
            ->context(array_merge(self::VALID_CONTEXT, [
                'user' => $user,
            ]));

        $event = new UserEmailMessageEvent(DummyUserEmailFactory::class, $emailMessage);
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $returnedEmailMessage = $userEmailFactory->create($user, self::VALID_CONTEXT);
        $this->assertEquals($emailMessage, $returnedEmailMessage);
    }

    public function test_do_not_create_email_message_if_not_enabled(): void
    {
        $userEmailFactory = new DummyUserEmailFactory(
            $this->containerInterfaceMock,
            $this->eventDispatcherMock,
            'subject',
            false
        );
        $user = new class() extends AbstractUser {
        };
        $user->setUsername('my_username')->setEmailAddress('email@address.com');

        $this->containerInterfaceMock
            ->expects($this->never())
            ->method('get');

        $this->eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');

        $returnedEmailMessage = $userEmailFactory->create($user, self::VALID_CONTEXT);
        $this->assertNull($returnedEmailMessage);
    }

    public function test_dummy_get_token_url_throws_exception_if_no_paths(): void
    {
        $userEmailFactory = new DummyUserEmailFactory(
            $this->containerInterfaceMock,
            $this->eventDispatcherMock,
            'subject'
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The `defaultRedirectPath` or `redirectPathQueryKey` must be set');
        $userEmailFactory->dummyGetTokenUrl(new class() extends AbstractUser {
        });
    }

    public function test_dummy_get_token_url_throws_exception_if_no_default_path_and_no_query_in_current_request(): void
    {
        $userEmailFactory = new DummyUserEmailFactory(
            $this->containerInterfaceMock,
            $this->eventDispatcherMock,
            'subject',
            true,
            null,
            'queryKey'
        );

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects($this->once())
            ->method('getMasterRequest')
            ->willReturn(new Request());

        $this->containerInterfaceMock
            ->expects($this->once())
            ->method('get')
            ->with(RequestStack::class)
            ->willReturn($requestStackMock);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(sprintf('The querystring key `%s` could not be found in the request to generate a token URL', 'queryKey'));
        $userEmailFactory->dummyGetTokenUrl(new class() extends AbstractUser {
        });
    }

    public function test_dummy_get_token_url_can_get_path_from_querystring_over_default_path(): void
    {
        $userEmailFactory = new DummyUserEmailFactory(
            $this->containerInterfaceMock,
            $this->eventDispatcherMock,
            'subject',
            true,
            '/a-default-path',
            'queryKey'
        );

        $request = new Request();
        $request->query->set('queryKey', '/query-path');

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects($this->once())
            ->method('getMasterRequest')
            ->willReturn($request);

        $this->containerInterfaceMock
            ->expects($this->at(0))
            ->method('get')
            ->with(RequestStack::class)
            ->willReturn($requestStackMock);

        $refererUrlMock = $this->createMock(RefererUrlHelper::class);
        $this->containerInterfaceMock
            ->expects($this->at(1))
            ->method('get')
            ->with(RefererUrlHelper::class)
            ->willReturn($refererUrlMock);

        $refererUrlMock
            ->expects($this->once())
            ->method('getAbsoluteUrl')
            ->with('/query-path')
            ->willReturn('/any-path');

        $this->assertEquals('/any-path', $userEmailFactory->dummyGetTokenUrl(new class() extends AbstractUser {
        }));
    }

    public function test_dummy_get_token_url_can_get_path_from_default_path(): void
    {
        $userEmailFactory = new DummyUserEmailFactory(
            $this->containerInterfaceMock,
            $this->eventDispatcherMock,
            'subject',
            true,
            '/a-default-path',
            'queryKey'
        );

        $request = new Request();

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects($this->once())
            ->method('getMasterRequest')
            ->willReturn($request);

        $this->containerInterfaceMock
            ->expects($this->at(0))
            ->method('get')
            ->with(RequestStack::class)
            ->willReturn($requestStackMock);

        $refererUrlMock = $this->createMock(RefererUrlHelper::class);
        $this->containerInterfaceMock
            ->expects($this->at(1))
            ->method('get')
            ->with(RefererUrlHelper::class)
            ->willReturn($refererUrlMock);

        $refererUrlMock
            ->expects($this->once())
            ->method('getAbsoluteUrl')
            ->with('/a-default-path')
            ->willReturn('/any-path');

        $this->assertEquals('/any-path', $userEmailFactory->dummyGetTokenUrl(new class() extends AbstractUser {
        }));
    }

    public function test_token_path_variable_populate(): void
    {
        $userEmailFactory = new DummyUserEmailFactory(
            $this->containerInterfaceMock,
            $this->eventDispatcherMock,
            'subject',
            true,
            '/path/{{username}}/{{ token }}'
        );

        $request = new Request();

        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock
            ->expects($this->once())
            ->method('getMasterRequest')
            ->willReturn($request);

        $this->containerInterfaceMock
            ->expects($this->at(0))
            ->method('get')
            ->with(RequestStack::class)
            ->willReturn($requestStackMock);

        $refererUrlMock = $this->createMock(RefererUrlHelper::class);
        $this->containerInterfaceMock
            ->expects($this->at(1))
            ->method('get')
            ->with(RefererUrlHelper::class)
            ->willReturn($refererUrlMock);

        $refererUrlMock
            ->expects($this->once())
            ->method('getAbsoluteUrl')
            ->with('/path/token%20username/my_token')
            ->willReturn('/any-path');

        $user = new class() extends AbstractUser {
        };
        $user->setUsername('token username');
        $this->assertEquals('/any-path', $userEmailFactory->dummyGetTokenUrl($user));
    }
}
