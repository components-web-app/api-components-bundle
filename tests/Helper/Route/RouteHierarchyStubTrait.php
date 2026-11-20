<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Route;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

trait RouteHierarchyStubTrait
{
    /**
     * @param list<AbstractPage> $pages
     * @param list<string>       $takenPaths
     */
    private function hierarchy(array $pages, array $takenPaths = []): EntityManagerInterface
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap($this->repositories($pages, $takenPaths));

        return $entityManager;
    }

    /**
     * @param list<AbstractPage> $pages
     * @param list<string>       $takenPaths
     */
    private function mockHierarchy(array $pages, array $takenPaths = []): EntityManagerInterface&MockObject
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap($this->repositories($pages, $takenPaths));

        return $entityManager;
    }

    /**
     * @param list<AbstractPage> $pages
     * @param list<string>       $takenPaths
     *
     * @return list<array{string, EntityRepository<object>}>
     */
    private function repositories(array $pages, array $takenPaths): array
    {
        $children = static fn (string $class): \Closure => static function (array $criteria) use ($pages, $class): array {
            $parent = $criteria['parentPage'] ?? $criteria['parentPageData'] ?? null;
            $field = \array_key_exists('parentPage', $criteria) ? 'parentPage' : 'parentPageData';
            $found = [];
            foreach ($pages as $page) {
                $pageParent = 'parentPage' === $field ? $page->getParentPage() : $page->getParentPageData();
                if ($page instanceof $class && null !== $parent && $pageParent === $parent) {
                    $found[] = $page;
                }
            }

            return $found;
        };
        $pageRepository = $this->createStub(EntityRepository::class);
        $pageRepository->method('findBy')->willReturnCallback($children(Page::class));
        $pageDataRepository = $this->createStub(EntityRepository::class);
        $pageDataRepository->method('findBy')->willReturnCallback($children(AbstractPageData::class));
        $routeRepository = $this->createStub(EntityRepository::class);
        $routeRepository->method('count')->willReturnCallback(static fn (array $criteria): int => \in_array($criteria['path'] ?? null, $takenPaths, true) ? 1 : 0);

        return [
            [Page::class, $pageRepository],
            [AbstractPageData::class, $pageDataRepository],
            [Route::class, $routeRepository],
        ];
    }

    private static function routed(AbstractPage $page, string $path): AbstractPage
    {
        $route = (new Route())->setPath($path)->setName($path);
        if ($page instanceof Page) {
            $route->setPage($page);
        }
        if ($page instanceof AbstractPageData) {
            $route->setPageData($page);
        }

        return $page;
    }

    private static function routeOf(AbstractPage $page): Route
    {
        return $page->getRoute() ?? throw new \LogicException('The page has no route.');
    }

    private function redirectingGenerator(): RouteGeneratorInterface
    {
        $generator = $this->createStub(RouteGeneratorInterface::class);
        $generator->method('createRedirect')->willReturnCallback(static fn (string $from, Route $to): Route => (new Route())->setPath($from)->setRedirect($to));

        return $generator;
    }

    private static function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
