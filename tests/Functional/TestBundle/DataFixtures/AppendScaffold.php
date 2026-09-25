<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class AppendScaffold extends AbstractCwaScaffold implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['cwa_append'];
    }

    public function build(CwaFixtureBuilder $cwa): void
    {
        $cwa->layout('append-main', 'Primary');
        $cwa->page('append-home', 'Primary', layout: 'append-main', route: '/append-home', routeName: 'append-home', configure: static function (PageBuilder $page): void {
            $page->group('primary')->add(new DummyComponent());
        });
    }
}
