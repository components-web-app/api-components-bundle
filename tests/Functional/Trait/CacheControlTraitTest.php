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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

final class CacheControlTraitTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CacheControlTrait;
    use SiteTrait;

    public function test_a_scheduled_go_live_caps_the_shared_max_age(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable('+60 seconds'));

        $client->request('GET', '/_/routes', ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSharedMaxAgeAtMost(60);
        self::assertGreaterThan(0, self::sharedMaxAge());
    }

    public function test_a_response_with_no_pending_transition_keeps_its_shared_max_age(): void
    {
        $client = static::createClient();
        self::createPageDataWithRoute('/my-route');

        $client->request('GET', '/_/resource_manifest//my-route', ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSharedMaxAgeAtLeast(600);
    }

    public function test_a_response_without_a_shared_max_age_fails_to_give_one(): void
    {
        static::createClient()->request('GET', '/_/health');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('has no s-maxage directive');

        self::sharedMaxAge();
    }

    public function test_a_throttled_request_says_how_many_seconds_to_wait(): void
    {
        $client = static::createClient();
        $user = self::createUser(['ROLE_USER'], 'my_username', 'test.user@example.com');
        $manager = self::getEntityManager();
        $managed = $manager->find(User::class, $user->getId());
        $managed->setNewPasswordConfirmationToken(bin2hex(random_bytes(8)))->setPasswordRequestedAt(new \DateTime('-1 hour'));
        $manager->flush();
        self::rebootKernel();

        $client->request('GET', '/password/reset/request/my_username', ['headers' => ['accept' => 'application/ld+json', 'referer' => 'http://www.website.com']]);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderIsSecondsBetween('Retry-After', 82700, 82800);
    }
}
