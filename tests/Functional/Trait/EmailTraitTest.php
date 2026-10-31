<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use PHPUnit\Framework\AssertionFailedError;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class EmailTraitTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use EmailTrait;
    use ResourceTrait;

    private const array HEADERS = ['accept' => 'application/ld+json', 'referer' => 'http://www.website.com'];

    public function test_a_password_reset_request_sends_one_templated_email_with_a_link(): void
    {
        $client = static::createClient();
        self::createUser(['ROLE_USER'], 'my_username', 'test.user@example.com');
        self::rebootKernel();

        $client->request('GET', '/password/reset/request/my_username', ['headers' => self::HEADERS]);

        self::assertResponseStatusCodeSame(200);
        $email = self::assertOneTemplatedEmailSentTo('test.user@example.com');
        self::assertSame('Your password reset request', $email->getSubject());
        self::assertMatchesRegularExpression('/^http:\/\/www.website.com\/reset-password\/my_username\/[^\/]+$/', self::sentEmailLink());
    }

    public function test_links_in_user_emails_can_default_to_another_origin(): void
    {
        $client = static::createClient();
        self::createUser(['ROLE_USER'], 'my_username', 'test.user@example.com');
        self::rebootKernel();
        self::setEmailLinkDefaultOrigin('https://default.example.com');

        $client->request('GET', '/password/reset/request/my_username', ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertStringStartsWith('https://default.example.com/', self::sentEmailLink());
    }

    public function test_a_request_for_an_unknown_user_sends_no_email(): void
    {
        $client = static::createClient();

        $client->request('GET', '/password/reset/request/nobody', ['headers' => self::HEADERS]);

        self::assertResponseStatusCodeSame(404);
        self::assertNoEmailSent();
        self::assertSame([], self::sentTemplatedEmails());
    }

    public function test_each_of_several_emails_is_found_by_its_recipient(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);

        $client->request('PATCH', self::iriOf($user), ['headers' => self::HEADERS + ['content-type' => 'application/merge-patch+json'], 'json' => ['newEmailAddress' => 'new@example.com', 'oldPassword' => 'password']]);

        self::assertResponseStatusCodeSame(200);
        self::assertCount(2, self::sentTemplatedEmails());
        self::assertSame('Please confirm your new email address', self::assertTemplatedEmailSentTo('new@example.com')->getSubject());
        self::assertSame('Your email address is being changed', self::assertTemplatedEmailSentTo('user@example.com')->getSubject());
    }

    public function test_no_email_to_an_address_fails_the_recipient_assertion(): void
    {
        $client = static::createClient();
        self::createUser(['ROLE_USER'], 'my_username', 'test.user@example.com');
        self::rebootKernel();
        $client->request('GET', '/password/reset/request/my_username', ['headers' => self::HEADERS]);

        $this->expectException(AssertionFailedError::class);

        self::assertTemplatedEmailSentTo('someone.else@example.com');
    }

    public function test_an_email_to_another_address_fails_the_assertion(): void
    {
        $client = static::createClient();
        self::createUser(['ROLE_USER'], 'my_username', 'test.user@example.com');
        self::rebootKernel();
        $client->request('GET', '/password/reset/request/my_username', ['headers' => self::HEADERS]);

        $this->expectException(AssertionFailedError::class);

        self::assertOneTemplatedEmailSentTo('someone.else@example.com');
    }
}
