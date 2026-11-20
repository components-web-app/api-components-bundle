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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\AnnouncementPageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\AnnouncementUpdatePageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;

final class RoutePrefixFixtureTest extends FunctionalTestCase
{
    use FixtureScenarioTrait;

    public function test_fixture_page_data_of_a_prefixed_class_gets_a_route_under_its_prefix(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasATemplatePage('template', 'main');
        $this->scaffoldHasAGeneratedPageDataOfTheClass(AnnouncementPageData::class, 'Launch', 'template');

        $this->loadScaffold();

        $this->assertRouteExists('/announcements/launch');
    }

    public function test_nested_fixture_page_data_composes_the_parent_path_and_its_own_prefix(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasATemplatePage('template', 'main');
        $this->scaffoldHasAPageDataAtTheRoute('Conference', '/conference', 'template');
        $this->scaffoldHasAGeneratedPageDataOfTheClass(AnnouncementPageData::class, 'Launch', 'template', 'Conference');
        $this->scaffoldHasAGeneratedPageDataOfTheClass(AnnouncementUpdatePageData::class, 'First', 'template', 'Launch');
        $this->scaffoldHasAGeneratedPageDataOfTheClass(PageData::class, 'Agenda', 'template', 'Launch');

        $this->loadScaffold();

        $this->assertRouteExists('/conference/announcements/launch');
        $this->assertRouteExists('/conference/announcements/launch/updates/first');
        $this->assertRouteExists('/conference/announcements/launch/agenda');
    }

    public function test_an_append_load_keeps_prefixed_page_data_found_at_its_prefixed_path(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasATemplatePage('template', 'main');
        $this->scaffoldHasAGeneratedPageDataOfTheClass(AnnouncementPageData::class, 'Launch', 'template');
        $this->loadScaffold();
        $this->recordDatabaseContents();

        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
        $this->assertLastLoadSummaryContains('kept 1 page, 1 page data');
    }
}
