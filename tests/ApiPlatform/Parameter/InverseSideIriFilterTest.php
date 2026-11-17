<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\ApiPlatform\Parameter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\State\ParameterProvider\IriConverterParameterProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Parameter\InverseSideIriFilter;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

final class InverseSideIriFilterTest extends TestCase
{
    use PublishableDatabaseTrait;

    /** @var array<string, Page> */
    private array $pages = [];

    private PageData $pageData;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $entities = [];
        foreach (['/one', '/two', '/three'] as $path) {
            $entities[] = $this->pages[$path] = $this->newPage($path);
            $entities[] = (new Route())->setPath($path)->setName($path)->setPage($this->pages[$path]);
        }
        $entities[] = $template = $this->newPage('template');
        $entities[] = $this->pageData = new PageData();
        $this->pageData->page = $template;
        $entities[] = (new Route())->setPath('/data')->setName('/data')->setPageData($this->pageData);
        foreach ($entities as $entity) {
            $entity->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function test_it_takes_its_values_from_the_iri_converter_parameter_provider(): void
    {
        self::assertSame(IriConverterParameterProvider::class, InverseSideIriFilter::getParameterProvider());
    }

    public function test_a_list_of_resources_matches_the_route_of_each(): void
    {
        self::assertSame(['/one', '/three'], $this->filteredPaths(['page' => [$this->pages['/one'], $this->pages['/three']]]));
    }

    public function test_a_single_resource_matches_its_route(): void
    {
        self::assertSame(['/two'], $this->filteredPaths(['page' => $this->pages['/two']]));
    }

    public function test_no_resource_matches_no_route(): void
    {
        self::assertSame([], $this->filteredPaths(['page' => []]));
    }

    public function test_the_parameter_key_is_the_property_when_none_is_given(): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('o')->from(Route::class, 'o');
        $parameter = new QueryParameter(key: 'pageData');
        $parameter->setValue([$this->pageData]);

        (new InverseSideIriFilter())->apply($queryBuilder, new QueryNameGenerator(), Route::class, null, ['parameter' => $parameter]);

        self::assertSame(['/data'], array_map(static fn (Route $route): string => $route->getPath(), $queryBuilder->getQuery()->getResult()));
    }

    public function test_the_inverse_side_named_by_the_property_is_the_one_compared(): void
    {
        self::assertSame(['/data'], $this->filteredPaths(['pageData' => [$this->pageData, $this->pages['/one']]]));
    }

    public function test_two_filters_on_one_query_both_apply(): void
    {
        self::assertSame([], $this->filteredPaths(['page' => [$this->pages['/one']], 'pageData' => [$this->pageData]]));
        self::assertSame(['/one'], $this->filteredPaths(['page' => [$this->pages['/one']], 'pageData' => [$this->pageData, $this->pages['/one']]], ['page', 'page']));
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>|null    $properties
     *
     * @return list<string>
     */
    private function filteredPaths(array $values, ?array $properties = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('o')->from(Route::class, 'o')->orderBy('o.path');
        $queryNameGenerator = new QueryNameGenerator();
        $filter = new InverseSideIriFilter();
        foreach (array_values($values) as $index => $value) {
            $parameter = new QueryParameter(key: array_keys($values)[$index], property: $properties[$index] ?? array_keys($values)[$index]);
            $parameter->setValue($value);
            $filter->apply($queryBuilder, $queryNameGenerator, Route::class, null, ['parameter' => $parameter]);
        }

        return array_map(static fn (Route $route): string => $route->getPath(), $queryBuilder->getQuery()->getResult());
    }

    private function newPage(string $reference): Page
    {
        $page = new Page();
        $page->reference = $reference;
        $page->isTemplate = false;

        return $page;
    }
}
