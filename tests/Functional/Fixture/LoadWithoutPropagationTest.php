<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Fixture;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;

final class LoadWithoutPropagationTest extends FunctionalTestCase
{
    use FixtureScenarioTrait;
    use MercureTrait;

    public function test_loading_fixtures_publishes_no_mercure_update_and_generates_no_imagine_variant(): void
    {
        $this->runCommandLine('doctrine:fixtures:load', 'cwa_image');

        self::assertNoMercureUpdatePublished();
        self::assertSame(0, self::countFileInfoRows('thumbnail'));
        self::assertSame(0, self::countFileInfoRows());
        $this->assertCommandOutputContains('The configured HTTP cache purger cannot flush the whole cache, so nothing was flushed');
        $this->assertCommandOutputContains('Run silverback:api-components:imagine:generate to generate the image variants');
    }
}
