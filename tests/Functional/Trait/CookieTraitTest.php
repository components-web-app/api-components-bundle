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

final class CookieTraitTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CookieTrait;

    public function test_logging_in_sets_the_jwt_and_mercure_cookies(): void
    {
        $client = static::createClient();
        self::createUser(['ROLE_ADMIN']);
        self::rebootKernel();

        $client->request('POST', '/login', ['json' => ['username' => 'new_user', 'password' => 'password']]);

        self::assertResponseStatusCodeSame(204);
        self::assertNotEmpty(self::responseCookie('api_components')->getValue());
        self::assertResponseCookieExpiresWithin('api_components', 604802);
        self::assertMercureCookieGrantsTopicMatching('/\/_\/component_groups\/:id\\\\\?draft=1$/');
        self::assertMercureCookieGrantsNoTopicMatching('/\/no_such_resources\//');
        self::assertMercureCookieAndTokenExpireIn(604800);
        self::assertContains('subscribe', self::mercureCookiePayload()['authorization_details'][0]['actions']);
    }

    public function test_a_response_that_sets_no_cookie_has_none(): void
    {
        static::createClient()->request('GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHasNoCookie(self::mercureCookieName());
    }

    public function test_asking_for_a_cookie_the_response_did_not_set_fails(): void
    {
        static::createClient()->request('GET', '/me');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The response sets no "api_components" cookie.');

        self::responseCookie('api_components');
    }

    public function test_a_mercure_cookie_with_draft_grants_and_no_login_is_cleared(): void
    {
        $client = static::createClient();
        self::sendMercureCookieWithDraftTopics($client);

        $client->request('GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertMercureCookieCleared();
    }
}
