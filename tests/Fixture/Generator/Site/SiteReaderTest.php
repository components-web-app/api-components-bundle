<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Site;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteReader;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteSnapshot;
use Silverback\ApiComponentsBundle\Tests\Fixture\Generator\GeneratorSiteTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;

class SiteReaderTest extends TestCase
{
    use GeneratorSiteTrait;

    public function test_layouts_are_ordered_by_reference(): void
    {
        $this->layout('secondary', 'CwaLayoutPrimary');
        $this->layout('main', 'CwaLayoutPrimary');

        self::assertSame(['main', 'secondary'], array_map(static fn (Layout $layout): ?string => $layout->reference, $this->read()->layouts));
    }

    public function test_pages_are_routed_by_path_then_routeless_by_reference_then_templates_by_reference(): void
    {
        $this->page('zeta-template', null)->isTemplate = true;
        $this->page('alpha-template', null)->isTemplate = true;
        $this->page('zulu', null);
        $this->page('alpha', null);
        $this->page('contact', null, $this->route('/contact', 'contact'));
        $this->page('home', null, $this->route('/', 'home'));

        self::assertSame(['home', 'contact', 'alpha', 'zulu', 'alpha-template', 'zeta-template'], $this->references($this->read()->pages));
    }

    public function test_page_data_is_routed_by_path_then_routeless_by_title(): void
    {
        foreach (['Zulu' => null, 'Alpha' => null, 'Second' => '/b', 'First' => '/a'] as $title => $path) {
            $pageData = new PageData();
            $pageData->setTitle($title);
            if (null !== $path) {
                $this->route($path, strtolower($title))->setPageData($pageData);
            }
            $this->repositories[AbstractPageData::class][] = $pageData;
        }

        self::assertSame(['First', 'Second', 'Alpha', 'Zulu'], array_map(static fn (AbstractPageData $pageData): ?string => $pageData->getTitle(), $this->read()->pageData));
    }

    public function test_children_are_pages_then_page_data_each_in_site_map_order_and_not_top_level(): void
    {
        $parent = $this->page('parent', null, $this->route('/parent', 'parent'));
        $template = $this->page('child-template', null);
        $template->isTemplate = true;
        $template->setParentPage($parent);
        $routed = $this->page('child-routed', null, $this->route('/parent/child', 'child'));
        $routed->setParentPage($parent);
        $pageData = new PageData();
        $pageData->setTitle('Child data');
        $pageData->setParentPage($parent);
        $this->repositories[AbstractPageData::class][] = $pageData;

        $site = $this->read();

        self::assertSame(['parent'], $this->references($site->pages));
        self::assertSame([], $site->pageData);
        self::assertSame([$routed, $template, $pageData], $site->childrenOf($parent));
        self::assertSame([], $site->childrenOf($routed));
    }

    public function test_redirects_come_after_their_targets_then_by_path_and_one_without_a_page_is_unresolved(): void
    {
        $about = $this->route('/about', 'about');
        $this->page('about', null, $about);
        $older = $this->route('/older', 'older');
        $old = $this->route('/old', 'old');
        $apple = $this->route('/apple', 'apple');
        $orphan = $this->route('/orphan', 'orphan');
        $older->setRedirect($old);
        $old->setRedirect($about);
        $apple->setRedirect($about);
        $orphan->setRedirect($this->route('/nowhere', 'nowhere'));
        $this->repositories[Route::class] = [$older, $about, $orphan, $old, $apple];

        $site = $this->read();

        self::assertSame(['/apple', '/old', '/older'], array_map(static fn (Route $route): string => $route->getPath(), $site->redirects));
        self::assertSame([$orphan], $site->unresolvedRedirects);
        self::assertSame([$about], $site->routes);
        self::assertTrue($site->contains($about));
        self::assertTrue($site->contains($older));
        self::assertFalse($site->contains($orphan));
    }

    public function test_a_route_that_is_neither_a_redirect_nor_in_the_site_is_left_out(): void
    {
        $about = $this->route('/about', 'about');
        $this->page('about', null, $about);
        $elsewhere = $this->route('/elsewhere', 'elsewhere');
        $this->repositories[Route::class] = [$elsewhere, $about];

        $site = $this->read();

        self::assertSame([], $site->redirects);
        self::assertSame([], $site->unresolvedRedirects);
        self::assertFalse($site->contains($elsewhere));
    }

    public function test_unresolved_redirects_are_ordered_by_path(): void
    {
        $nowhere = $this->route('/nowhere', 'nowhere');
        $zebra = $this->route('/zebra', 'zebra');
        $zebra->setRedirect($nowhere);
        $apple = $this->route('/apple', 'apple');
        $apple->setRedirect($nowhere);
        $this->repositories[Route::class] = [$zebra, $apple];

        self::assertSame([$apple, $zebra], $this->read()->unresolvedRedirects);
    }

    public function test_the_children_of_every_top_level_page_and_page_data_are_reached(): void
    {
        $parents = [$this->page('first', null, $this->route('/first', 'first')), $this->page('second', null, $this->route('/second', 'second'))];
        foreach (['One' => '/one', 'Two' => '/two'] as $title => $path) {
            $pageData = new PageData();
            $pageData->setTitle($title);
            $this->route($path, strtolower($title))->setPageData($pageData);
            $this->repositories[AbstractPageData::class][] = $pageData;
            $parents[] = $pageData;
        }
        $children = [];
        foreach ($parents as $index => $parent) {
            $child = $this->page('child-' . $index, null, $this->route('/child-' . $index, 'child-' . $index));
            $parent instanceof Page ? $child->setParentPage($parent) : $child->setParentPageData($parent);
            $children[] = $child;
        }

        $site = $this->read();

        self::assertSame([], $site->unreached);
        foreach ($children as $child) {
            self::assertTrue($site->contains($child->getRoute()));
        }
    }

    public function test_pages_nested_in_a_cycle_are_unreached(): void
    {
        $first = $this->page('first', null);
        $second = $this->page('second', null);
        $first->setParentPage($second);
        $second->setParentPage($first);

        $site = $this->read();

        self::assertSame([], $site->pages);
        self::assertSame([$first, $second], $site->unreached);
    }

    private function read(): SiteSnapshot
    {
        return (new SiteReader($this->registry()))->read();
    }

    /**
     * @param list<AbstractPage> $pages
     *
     * @return list<?string>
     */
    private function references(array $pages): array
    {
        return array_map(static fn (AbstractPage $page): ?string => $page instanceof Page ? $page->reference : null, $pages);
    }
}
