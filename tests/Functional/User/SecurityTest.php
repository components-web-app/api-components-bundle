<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\User;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Form\Type\User\UserLoginType;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SecurityTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_i_can_use_a_login_form(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(UserLoginType::class);

        self::requestJsonLd($client, 'GET', $formIri);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }

    public function test_a_disabled_user_is_not_able_to_login(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        $user = self::createUserWithRole('user', $password, 'ROLE_USER');
        self::disableUser($user);

        $response = self::login($client, 'user', $password);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['code' => 401, 'message' => 'Your account is currently disabled.'], $response->toArray(false));
    }

    public function test_a_successful_login(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        self::createUserWithRole('user', $password, 'ROLE_USER');

        $response = self::login($client, 'user', $password);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
        self::assertResponseCookieAttributes('api_components', '/', 'lax');
        self::assertRefreshTokenCount(1);
        self::assertResponseCookieAttributes(self::mercureCookieName(), '/.well-known/mercure', 'strict');
        self::assertEmpty(self::draftTopics(), 'The cookie allows a user to be subscribed to draft resources');
        self::assertMercureCookieAndTokenExpireIn(604800);
    }

    public function test_a_logged_in_user_can_fetch_a_list_of_all_users(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::createUserWithRole('another_user', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'GET', '/users?perPage=5');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 2);
        self::assertJsonNodeMatches('member[0].@id', '/^\/users\/([a-zA-Z0-9\-]+)$/');
        self::assertJsonNodeMatches('member[1].@id', '/^\/users\/([a-zA-Z0-9\-]+)$/');
    }

    public function test_a_successful_admin_login_grants_draft_resource_topics(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        self::createUserWithRole('admin', $password, 'ROLE_ADMIN');

        $response = self::login($client, 'admin', $password);

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', $response->getContent());
        self::assertResponseCookieAttributes(self::mercureCookieName(), '/.well-known/mercure', 'strict');
        self::assertNotEmpty(self::draftTopics(), 'The cookie does not allow a user to subscribe to any draft resources');
    }

    public function test_an_admins_mercure_cookie_grants_the_admin_topic_of_a_resource_class_that_is_not_publishable(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        self::createUserWithRole('admin', $password, 'ROLE_ADMIN');

        self::login($client, 'admin', $password);

        self::assertResponseStatusCodeSame(204);
        self::assertMercureCookieGrantsTopicMatching('/\/_\/component_groups\/:id\\\\\?draft=1$/');
    }

    public function test_a_users_mercure_cookie_does_not_grant_the_admin_topic_of_a_resource_class_that_is_not_publishable(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        self::createUserWithRole('user', $password, 'ROLE_USER');

        self::login($client, 'user', $password);

        self::assertResponseStatusCodeSame(204);
        self::assertMercureCookieGrantsTopicMatching('/\/_\/component_groups\/:id\\\\\?$/');
        self::assertMercureCookieGrantsNoTopicMatching('/\/_\/component_groups\/:id\\\\\?draft=1$/');
    }

    public function test_a_request_to_me_with_no_cookies_is_a_401_that_sets_no_mercure_cookie(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHasNoCookie(self::mercureCookieName());
    }

    public function test_a_request_to_me_carrying_only_a_mercure_cookie_with_draft_grants_is_a_401_that_clears_the_mercure_cookie(): void
    {
        $client = static::createClient();
        self::sendMercureCookieWithDraftTopics($client);

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertMercureCookieCleared();
    }

    public function test_expired_jwt_tokens_should_be_refreshed(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::setExpiredJwtCookie($client, $user);
        self::createRefreshToken($user);

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(200);
        self::assertRefreshTokenExpired($user);
        self::assertResponseCookieAttributes('api_components', '/', 'lax');
        self::assertResponseCookieAttributes(self::mercureCookieName(), '/.well-known/mercure', 'strict');
        self::assertRefreshTokenCount(2);
    }

    public function test_a_user_with_an_expired_refresh_token_and_an_expired_access_token_gets_a_401(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::createRefreshToken($user, '-1 second');
        self::setExpiredJwtCookie($client, $user);

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertJwtCookieCleared();
        self::assertMercureCookieCleared();
    }

    public function test_an_admin_with_an_expired_refresh_token_and_an_expired_access_token_gets_a_401_and_a_cleared_mercure_cookie(): void
    {
        $client = static::createClient();
        $user = self::loginAsAdmin($client);
        self::createRefreshToken($user, '-1 second');
        self::setExpiredJwtCookie($client, $user);

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertJwtCookieCleared();
        self::assertMercureCookieCleared();
    }

    #[DataProvider('logoutMethods')]
    public function test_a_user_with_a_valid_refresh_token_who_logs_out_has_all_their_refresh_tokens_expired(string $method): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::createRefreshToken($user);

        self::requestJsonLd($client, $method, '/logout');

        self::assertResponseStatusCodeSame(200);
        self::assertRefreshTokenCount(1);
        self::assertAllRefreshTokensExpired($user);
        self::assertJwtCookieCleared();
        self::assertMercureCookieCleared();
    }

    public function test_i_can_logout_even_if_i_have_no_refresh_token(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/logout');

        self::assertResponseStatusCodeSame(200);
        self::assertRefreshTokenCount(1);
        self::assertAllRefreshTokensExpired($user);
        self::assertJwtCookieCleared();
        self::assertMercureCookieCleared();
    }

    public function test_i_can_logout_even_if_the_user_has_been_deleted_while_logged_in(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::removeUser($user);

        self::requestJsonLd($client, 'POST', '/logout');

        self::assertResponseStatusCodeSame(200);
        self::assertRefreshTokenCount(0);
        self::assertJwtCookieCleared();
        self::assertMercureCookieCleared();
    }

    #[DataProvider('logoutMethods')]
    public function test_an_admin_who_logs_out_gets_a_cleared_mercure_cookie(string $method): void
    {
        $client = static::createClient();
        $user = self::loginAsAdmin($client);
        self::createRefreshToken($user);

        self::requestJsonLd($client, $method, '/logout');

        self::assertResponseStatusCodeSame(200);
        self::assertAllRefreshTokensExpired($user);
        self::assertSame('x.x.x', self::responseCookie('api_components')->getValue());
        self::assertResponseCookieAttributes(self::mercureCookieName(), '/.well-known/mercure', 'strict');
        self::assertMercureCookieCleared();
    }

    public function test_an_admin_with_no_refresh_token_who_logs_out_gets_a_cleared_mercure_cookie(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/logout');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('x.x.x', self::responseCookie('api_components')->getValue());
        self::assertMercureCookieCleared();
    }

    public function test_an_admin_deleted_while_logged_in_who_logs_out_gets_a_cleared_mercure_cookie(): void
    {
        $client = static::createClient();
        $user = self::loginAsAdmin($client);
        self::removeUser($user);

        self::requestJsonLd($client, 'POST', '/logout');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('x.x.x', self::responseCookie('api_components')->getValue());
        self::assertMercureCookieCleared();
    }

    public function test_jwt_tokens_that_are_invalid_should_be_removed_from_a_users_cookie_store_in_the_response_headers(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::setInvalidBearerToken($client);

        self::requestJsonLd($client, 'GET', '/');

        self::assertResponseStatusCodeSame(401);
        self::assertJwtCookieCleared();
        self::assertMercureCookieCleared();
    }

    public function test_i_cannot_submit_a_login_form_back_to_the_resource(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(UserLoginType::class);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', ['user_login' => []]);

        self::assertResponseStatusCodeSame(404);
    }

    public function test_with_secure_subscriptions_enabled_a_non_admin_users_mercure_cookie_excludes_admin_only_resource_topics(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        self::createUserWithRole('user', $password, 'ROLE_USER');

        self::login($client, 'user', $password);

        self::assertResponseStatusCodeSame(204);
        self::assertMercureCookieGrantsNoTopicMatching('/dummy_secured_mercure_resource/');
    }

    public function test_with_secure_subscriptions_enabled_an_admin_users_mercure_cookie_includes_admin_only_resource_topics(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        self::createUserWithRole('admin', $password, 'ROLE_ADMIN');

        self::login($client, 'admin', $password);

        self::assertResponseStatusCodeSame(204);
        self::assertMercureCookieGrantsTopicMatching('/dummy_secured_mercure_resource/');
    }

    public function test_with_secure_subscriptions_enabled_an_anonymous_request_to_me_sets_no_mercure_cookie(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHasNoCookie(self::mercureCookieName());
    }

    public function test_an_expired_access_token_that_cannot_be_refreshed_on_an_operation_other_than_me_is_a_401_that_clears_both_cookies(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::createRefreshToken($user, '-1 second');
        self::setExpiredJwtCookie($client, $user);

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(401);
        self::assertJwtCookieCleared();
        self::assertResponseCookieAttributes('api_components', '/', 'lax');
        self::assertMercureCookieCleared();
        self::assertResponseCookieAttributes(self::mercureCookieName(), '/.well-known/mercure', 'strict');
    }

    public function test_an_expired_access_token_that_is_refreshed_on_an_operation_other_than_me_sets_new_cookies(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::setExpiredJwtCookie($client, $user);
        self::createRefreshToken($user);

        self::requestJsonLd($client, 'GET', '/_/routes');

        self::assertResponseStatusCodeSame(200);
        self::assertNotSame('x.x.x', self::responseCookie('api_components')->getValue());
        self::assertFalse(self::responseCookie('api_components')->isCleared());
        self::assertMercureCookieAndTokenExpireIn(604800);
    }

    public static function logoutMethods(): iterable
    {
        yield 'GET' => ['GET'];
        yield 'POST' => ['POST'];
    }

    private static function login(Client $client, string $username, string $password): ResponseInterface
    {
        return self::requestJsonLd($client, 'POST', '/login', ['username' => $username, 'password' => $password]);
    }

    /**
     * @return list<string>
     */
    private static function draftTopics(): array
    {
        return array_values(array_filter(self::mercureCookieTopics(), static fn (string $topic): bool => str_ends_with($topic, '?draft=1')));
    }

    private static function removeUser(User $user): void
    {
        $manager = self::getEntityManager();
        $manager->remove($manager->find(User::class, $user->getId()));
        $manager->flush();
        self::rebootKernel();
    }
}
