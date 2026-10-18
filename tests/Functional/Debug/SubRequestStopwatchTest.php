<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Debug;

use PHPUnit\Framework\Attributes\Group;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CwaFixtureTrait;

#[Group('debug')]
final class SubRequestStopwatchTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CwaFixtureTrait;

    public function test_publishing_a_draft_whose_mercure_update_reads_it_anonymously_succeeds_under_a_debug_kernel_without_profiler_collection(): void
    {
        static::bootDebugKernel();
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = new DummyPublishableComponent();
        self::buildSite(static function (CwaFixtureBuilder $cwa) use ($draft): void {
            $cwa->layout('primary', 'Primary');
            $published = $cwa->publish(new DummyPublishableComponent());
            $cwa->page('home', 'Primary', 'primary', route: '/')->group('main')->add($published);
            $cwa->component($published)->draft($draft);
        });
        HubStub::clearPublished();

        $client->request('PATCH', '/component/dummy_publishable_components/' . $draft->getId(), [
            'headers' => ['accept' => 'application/ld+json', 'content-type' => 'application/merge-patch+json'],
            'body' => '{"publishedAt": "now"}',
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertNotSame([], HubStub::getPublished());
    }
}
