<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\QueryCountingMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class TemplatePageDataCountTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use SiteTrait;

    public function test_an_admin_reading_a_template_page_gets_the_number_of_page_data_of_every_class_using_it(): void
    {
        $client = self::adminClient();
        $template = self::createTemplateUsedBy('articles', new PageData(), new PageData(), new PageDataWithComponent());
        self::createTemplateUsedBy('events', new PageData());

        self::requestJsonLd($client, 'GET', self::iriOf($template));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.pageDataCount', 3);
    }

    public function test_an_admin_reading_a_template_page_nothing_uses_gets_a_count_of_zero(): void
    {
        $client = self::adminClient();
        $template = self::createPage('unused', true);

        self::requestJsonLd($client, 'GET', self::iriOf($template));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.pageDataCount', 0);
    }

    public function test_an_admin_reading_a_page_which_is_not_a_template_gets_no_count(): void
    {
        $client = self::adminClient();
        $page = self::createPage('plain');

        self::requestJsonLd($client, 'GET', self::iriOf($page));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('_metadata.pageDataCount');
    }

    public function test_the_page_collection_gives_an_admin_each_template_pages_count_and_none_for_other_pages(): void
    {
        $client = self::adminClient();
        self::createTemplateUsedBy('articles', new PageData(), new PageData(), new PageDataWithComponent());
        self::createTemplateUsedBy('events', new PageDataWithComponent());
        self::createPage('unused', true);
        self::createPage('plain');

        self::requestJsonLd($client, 'GET', '/_/pages');

        self::assertResponseStatusCodeSame(200);
        $counts = self::pageDataCountsByReference();
        self::assertSame(['articles' => 3, 'events' => 1, 'unused' => 0], $counts);
        self::assertArrayNotHasKey('plain', $counts);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function visitors(): iterable
    {
        yield 'an anonymous visitor' => [null];
        yield 'a signed-in user' => ['user'];
    }

    #[DataProvider('visitors')]
    public function test_a_visitor_reading_a_template_page_gets_no_count(?string $visitor): void
    {
        $client = static::createClient();
        if ('user' === $visitor) {
            self::loginAsUser($client);
        }
        $page = self::createPageDataWithRoute('/article')['page'];

        self::requestJsonLd($client, 'GET', self::iriOf($page));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('_metadata.pageDataCount');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function collectionSizes(): iterable
    {
        yield 'two template pages' => [2];
        yield 'ten template pages' => [10];
    }

    #[DataProvider('collectionSizes')]
    public function test_the_page_collection_counts_the_page_data_of_every_template_page_in_one_query(int $templates): void
    {
        $client = self::adminClient();
        for ($i = 0; $i < $templates; ++$i) {
            self::createTemplateUsedBy('template-' . $i, new PageData(), new PageDataWithComponent());
        }
        QueryCountingMiddleware::reset();

        self::requestJsonLd($client, 'GET', '/_/pages');

        self::assertResponseStatusCodeSame(200);
        self::assertCount($templates, self::pageDataCountsByReference());
        $table = static::getEntityManager()->getClassMetadata(AbstractPageData::class)->getTableName();
        $queries = QueryCountingMiddleware::queriesOnTable($table);
        self::assertCount(1, $queries, implode("\n", $queries));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function adminReads(): iterable
    {
        yield 'a template page' => ['item'];
        yield 'the page collection' => ['collection'];
    }

    #[DataProvider('adminReads')]
    public function test_an_admin_response_carrying_the_count_is_never_stored_by_a_shared_cache(string $read): void
    {
        $client = self::adminClient();
        $template = self::createTemplateUsedBy('articles', new PageData());

        self::requestJsonLd($client, 'GET', 'item' === $read ? self::iriOf($template) : '/_/pages');

        self::assertResponseStatusCodeSame(200);
        $cacheControl = (string) self::getClient()->getResponse()->headers->get('cache-control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringNotContainsString('s-maxage', $cacheControl);
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    private static function createTemplateUsedBy(string $reference, AbstractPageData ...$pageData): Page
    {
        $template = self::newPage($reference, true);
        foreach ($pageData as $item) {
            $item->page = $template;
        }
        self::persistAndFlush($template, ...$pageData);

        return $template;
    }

    /**
     * @return array<string, int>
     */
    private static function pageDataCountsByReference(): array
    {
        $counts = [];
        foreach (self::jsonNode('member') as $member) {
            if (\array_key_exists('pageDataCount', $member['_metadata'] ?? [])) {
                $counts[$member['reference']] = $member['_metadata']['pageDataCount'];
            }
        }
        ksort($counts);

        return $counts;
    }
}
