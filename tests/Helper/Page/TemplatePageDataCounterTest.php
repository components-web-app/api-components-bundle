<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Page;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Page\TemplatePageDataCounter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

class TemplatePageDataCounterTest extends OrphanedResourceDatabaseTestCase
{
    public function test_a_template_page_counts_the_page_data_of_every_class_using_it(): void
    {
        $articles = $this->templateUsedBy(new PageData(), new PageData(), new PageDataWithComponent());
        $this->templateUsedBy(new PageData());
        $this->entityManager->flush();

        self::assertSame(3, (new TemplatePageDataCounter($this->entityManager))->count($articles));
    }

    public function test_a_template_page_nothing_uses_counts_zero(): void
    {
        $unused = $this->page();
        $this->entityManager->flush();

        self::assertSame(0, (new TemplatePageDataCounter($this->entityManager))->count($unused));
    }

    public function test_every_template_page_prefetched_together_is_counted_in_one_query_and_then_answered_from_memory(): void
    {
        $articles = $this->templateUsedBy(new PageData(), new PageDataWithComponent());
        $events = $this->templateUsedBy(new PageData());
        $unused = $this->page();
        $this->entityManager->flush();
        $counter = new TemplatePageDataCounter($this->entityManager);
        $this->queryLogger->queries = [];

        $counter->prefetch([$articles, $events, $unused, new class {
            public bool $isTemplate = true;

            public function getId(): UuidInterface
            {
                return Uuid::uuid4();
            }
        }]);

        self::assertCount(1, $this->queryLogger->queries);
        $this->queryLogger->queries = [];
        self::assertSame([2, 1, 0], [$counter->count($articles), $counter->count($events), $counter->count($unused)]);
        self::assertSame([], $this->queryLogger->queries);
    }

    public function test_a_page_already_counted_is_not_counted_again_until_the_counter_is_reset(): void
    {
        $articles = $this->templateUsedBy(new PageData());
        $this->entityManager->flush();
        $counter = new TemplatePageDataCounter($this->entityManager);
        self::assertSame(1, $counter->count($articles));
        $added = new PageData();
        $added->page = $articles;
        $this->persist($added);
        $this->entityManager->flush();
        $this->queryLogger->queries = [];

        $counter->prefetch([$articles]);

        self::assertSame([], $this->queryLogger->queries);
        self::assertSame(1, $counter->count($articles));

        $counter->reset();

        self::assertSame(2, $counter->count($articles));
    }

    public function test_pages_which_are_not_templates_or_not_persisted_are_never_queried(): void
    {
        $plain = $this->page();
        $plain->isTemplate = false;
        $this->entityManager->flush();
        $unsaved = new Page();
        $unsaved->isTemplate = true;
        $counter = new TemplatePageDataCounter($this->entityManager);
        $this->queryLogger->queries = [];

        $counter->prefetch([$plain, $unsaved]);

        self::assertSame([], $this->queryLogger->queries);
        self::assertSame(0, $counter->count($unsaved));
        self::assertSame([], $this->queryLogger->queries);
    }

    private function templateUsedBy(AbstractPageData ...$pageData): Page
    {
        $template = $this->page();
        foreach ($pageData as $item) {
            $item->page = $template;
            $this->persist($item);
        }

        return $template;
    }
}
