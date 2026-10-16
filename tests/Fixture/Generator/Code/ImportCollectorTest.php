<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Code;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;

class ImportCollectorTest extends TestCase
{
    public function test_the_core_imports_are_always_there(): void
    {
        self::assertSame([
            'use Silverback\\ApiComponentsBundle\\Fixture\\AbstractCwaScaffold;',
            'use Silverback\\ApiComponentsBundle\\Fixture\\CwaFixtureBuilder;',
        ], (new ImportCollector('SiteScaffold'))->lines());
    }

    public function test_a_class_is_written_by_its_short_name_and_imported_once(): void
    {
        $imports = new ImportCollector('SiteScaffold');

        self::assertSame('Card', $imports->add('App\\Entity\\Card'));
        self::assertSame('Card', $imports->add('App\\Entity\\Card'));
        self::assertSame(1, substr_count(implode("\n", $imports->lines()), 'App\\Entity\\Card;'));
    }

    public function test_imports_are_sorted(): void
    {
        $imports = new ImportCollector('SiteScaffold');
        $imports->add('Symfony\\Component\\HttpFoundation\\File\\File');
        $imports->add('App\\Entity\\Zebra');
        $imports->add('App\\Entity\\apple');

        self::assertSame([
            'use App\\Entity\\apple;',
            'use App\\Entity\\Zebra;',
            'use Silverback\\ApiComponentsBundle\\Fixture\\AbstractCwaScaffold;',
            'use Silverback\\ApiComponentsBundle\\Fixture\\CwaFixtureBuilder;',
            'use Symfony\\Component\\HttpFoundation\\File\\File;',
        ], $imports->lines());
    }

    public function test_a_short_name_clash_gets_an_alias_prefixed_with_its_namespace(): void
    {
        $imports = new ImportCollector('SiteScaffold');

        self::assertSame('Card', $imports->add('App\\Entity\\Card'));
        self::assertSame('BlogCard', $imports->add('App\\Blog\\Card'));
        self::assertSame('BlogCard', $imports->add('App\\Blog\\Card'));
        self::assertContains('use App\\Blog\\Card as BlogCard;', $imports->lines());
        self::assertContains('use App\\Entity\\Card;', $imports->lines());
    }

    public function test_a_class_named_like_the_generated_class_gets_an_alias(): void
    {
        $imports = new ImportCollector('SiteScaffold');

        self::assertSame('EntitySiteScaffold', $imports->add('App\\Entity\\SiteScaffold'));
        self::assertContains('use App\\Entity\\SiteScaffold as EntitySiteScaffold;', $imports->lines());
    }

    public function test_a_core_import_is_not_imported_again(): void
    {
        $imports = new ImportCollector('SiteScaffold');

        self::assertSame('CwaFixtureBuilder', $imports->add(CwaFixtureBuilder::class));
        self::assertSame('AbstractCwaScaffold', $imports->add(AbstractCwaScaffold::class));
        self::assertCount(2, $imports->lines());
    }

    public function test_a_class_named_like_a_core_import_gets_an_alias(): void
    {
        $imports = new ImportCollector('SiteScaffold');

        self::assertSame('OtherCwaFixtureBuilder', $imports->add('App\\Other\\CwaFixtureBuilder'));
        self::assertContains('use App\\Other\\CwaFixtureBuilder as OtherCwaFixtureBuilder;', $imports->lines());
    }

    public function test_a_leading_separator_is_ignored(): void
    {
        $imports = new ImportCollector('SiteScaffold');

        self::assertSame('Card', $imports->add('\\App\\Entity\\Card'));
        self::assertContains('use App\\Entity\\Card;', $imports->lines());
    }
}
