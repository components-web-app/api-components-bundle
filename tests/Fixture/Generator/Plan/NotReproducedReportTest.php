<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Plan;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\NotReproducedReport;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;

class NotReproducedReportTest extends TestCase
{
    public function test_messages_are_kept_in_the_order_they_were_added(): void
    {
        $report = new NotReproducedReport($this->createStub(IriConverterInterface::class));
        $report->add('first');
        $report->add('second');

        self::assertSame(['first', 'second'], $report->all());
    }

    public function test_a_message_repeated_for_each_entity_is_one_line_with_a_count(): void
    {
        $report = new NotReproducedReport($this->createStub(IriConverterInterface::class));
        $first = new DummyComponent();
        $report->addForEach('App\\Entity\\ChartItem::$sheetData has no public property or setter to write it with.', $first);
        $report->add('An individual message.');
        $report->addForEach('App\\Entity\\ChartItem::$sheetData has no public property or setter to write it with.', new DummyComponent());
        $report->addForEach('App\\Entity\\ChartItem::$sheetData has no public property or setter to write it with.', $first);
        $report->addForEach('PageData::$secret has no public property or setter to write it with.', new PageData());
        $report->addForEach('PageData::$secret has no public property or setter to write it with.', new PageData());
        $report->addForEach('Layout::$secret has no public property or setter to write it with.', new Layout());
        $report->addForEach('Page::$secret has no public property or setter to write it with.', new Page());
        $report->addForEach('Page::$secret has no public property or setter to write it with.', new Page());

        self::assertSame([
            'App\\Entity\\ChartItem::$sheetData has no public property or setter to write it with (2 components).',
            'An individual message.',
            'PageData::$secret has no public property or setter to write it with (2 page data).',
            'Layout::$secret has no public property or setter to write it with (1 entity).',
            'Page::$secret has no public property or setter to write it with (2 entities).',
        ], $report->all());
    }

    public function test_a_message_for_one_component_says_so(): void
    {
        $report = new NotReproducedReport($this->createStub(IriConverterInterface::class));
        $report->addForEach('The collection X::$items', new DummyComponent());

        self::assertSame(['The collection X::$items (1 component).'], $report->all());
    }

    public function test_an_entity_is_described_by_its_iri_else_by_its_class(): void
    {
        $page = new Page();
        $layout = new Layout();
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static function (object $resource) use ($page): ?string {
            if ($resource === $page) {
                return '/_/pages/home';
            }
            throw new InvalidArgumentException('Not a resource.');
        });
        $report = new NotReproducedReport($iriConverter);

        self::assertSame('/_/pages/home', $report->describe($page));
        self::assertSame(Layout::class, $report->describe($layout));
        self::assertSame([], $report->all());
    }

    public function test_an_entity_with_no_iri_is_described_by_its_class(): void
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn(null);

        self::assertSame(Page::class, (new NotReproducedReport($iriConverter))->describe(new Page()));
    }
}
