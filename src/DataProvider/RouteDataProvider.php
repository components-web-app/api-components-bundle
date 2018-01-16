<?php

namespace Silverback\ApiComponentBundle\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use ApiPlatform\Core\Exception\RuntimeException;
use Silverback\ApiComponentBundle\Entity\Route;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectRepository;

final class RouteDataProvider implements ItemDataProviderInterface
{
    /**
     * @var ObjectRepository
     */
    private $managerRegistry;

    /**
     * LayoutDataProvider constructor.
     *
     * @param ManagerRegistry $managerRegistry
     */
    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @param string      $resourceClass
     * @param int|string  $id
     * @param string|null $operationName
     * @param array       $context
     *
     * @return null|Route|array
     * @throws ResourceClassNotSupportedException
     */
    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        if (null === $manager) {
            throw new ResourceClassNotSupportedException();
        }

        if (Route::class !== $resourceClass) {
            throw new ResourceClassNotSupportedException();
        }

        $repository = $manager->getRepository($resourceClass);
        if (!method_exists($repository, 'createQueryBuilder')) {
            throw new RuntimeException('The repository class must have a "createQueryBuilder" method.');
        }

        /**
         * @var null|Route $route
         */
        $route = $repository->find($id);
        // Route not found check
        if (!$route) {
            return null;
        }
        // Route redirect
        if ($route->getRedirect()) {
            return $route;
        }
        // Route pages
        $page = $route->getPage();
        $collection = [$page];
        while($parent = $page->getParent()) {
            array_unshift($collection, $parent);
            $page = $parent;
        }
        return $collection;
    }
}
