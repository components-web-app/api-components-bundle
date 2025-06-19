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

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class UserEnabledEmailFactoryTest extends AbstractFinalEmailFactoryTestCase
{
    public function test_skip_user_validation_if_disabled(): void
    {
        $factory = new UserEnabledEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', false);
        $this->assertNull(
            $factory->create(
                new class extends AbstractUser {
                }
            )
        );
    }

    public function test_redirect_url_context_added_and_html_template_passed(): void
    {
        $user = new class extends AbstractUser {
        };
        $user
            ->setUsername('username')
            ->setEmailAddress('email@address.com');

        $factory = new UserEnabledEmailFactory($this->containerInterfaceMock, $this->eventDispatcherMock, 'subject', true, '/default-path');

        $referrerUrlMock = $this->createMock(RefererUrlResolver::class);
        $referrerUrlMock
            ->method('getAbsoluteUrl')
            ->willReturn('https://login');
        $this->assertCommonMockMethodsCalled(false, [[
            [RefererUrlResolver::class],
            $referrerUrlMock,
        ]]);

        $email = (new TemplatedEmail())
            ->to(Address::create('email@address.com'))
            ->subject('subject')
            ->htmlTemplate('@SilverbackApiComponents/emails/user_enabled.html.twig')
            ->context(
                [
                    'website_name' => 'my website',
                    'user' => $user,
                    'login_url' => 'https://login',
                ]
            );

        $this->assertEmailEquals($email, $factory->create($user, ['website_name' => 'my website']), UserEnabledEmailFactory::MESSAGE_ID_PREFIX);
    }
}
