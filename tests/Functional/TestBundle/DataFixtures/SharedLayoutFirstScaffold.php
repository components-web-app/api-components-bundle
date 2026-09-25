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
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class SharedLayoutFirstScaffold extends AbstractCwaScaffold implements FixtureGroupInterface, OrderedFixtureInterface
{
    public static function getGroups(): array
    {
        return ['cwa_two_scaffolds'];
    }

    public function getOrder(): int
    {
        return 1;
    }

    public function build(CwaFixtureBuilder $cwa): void
    {
        $logo = new DummyComponent();
        $logo->uiComponent = 'Logo';
        $cwa->layout('shared-main', 'Primary')->group('top')->add($logo);
        $cwa->page('shared-home', 'Primary', layout: 'shared-main', route: '/shared-home', routeName: 'shared-home', configure: static function (PageBuilder $page): void {
            $hero = new DummyComponent();
            $hero->uiComponent = 'Hero';
            $page->group('primary')->add($hero);
        });
    }
}
