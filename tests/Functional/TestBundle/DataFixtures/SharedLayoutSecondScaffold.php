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

class SharedLayoutSecondScaffold extends AbstractCwaScaffold implements FixtureGroupInterface, OrderedFixtureInterface
{
    public static function getGroups(): array
    {
        return ['cwa_two_scaffolds'];
    }

    public function getOrder(): int
    {
        return 2;
    }

    public function build(CwaFixtureBuilder $cwa): void
    {
        $cwa->layout('shared-main', 'Primary');
        $cwa->page('shared-about', 'Primary', layout: 'shared-main', route: '/shared-about', routeName: 'shared-about', configure: static function (PageBuilder $page): void {
            $text = new DummyComponent();
            $text->uiComponent = 'About text';
            $page->group('primary')->add($text);
        });
        $cwa->redirect('/shared-old', to: 'shared-home');
    }
}
