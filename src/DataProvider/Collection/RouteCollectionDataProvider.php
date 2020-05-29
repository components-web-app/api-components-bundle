<?php


namespace Silverback\ApiComponentsBundle\DataProvider\Collection;

use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Symfony\Component\Security\Core\Security;

/**
 * This is expensive, however this endpoint is not usually required by an application
 * other than a super admin managing routes. Additionally, it'll be cached in our server
 * stack caching layers. It could prove useful for applications to build navigations
 * or site-maps otherwise we would have simply restricted getting collections of Routes
 * to ROLE_SUPER_ADMIN
 *
 * @author Daniel West <daniel@silverback.is>
 */
class RouteCollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private const ALREADY_CALLED = 'ROUTE_DATA_PROVIDER_ALREADY_CALLED';

    private ContextAwareCollectionDataProviderInterface $dataProvider;
    private Security $security;

    public function __construct(ContextAwareCollectionDataProviderInterface $dataProvider, Security $security)
    {
        $this->dataProvider = $dataProvider;
        $this->security = $security;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Route::class === $resourceClass && !isset($context[self::ALREADY_CALLED]);
    }

    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var Route[]|Paginator $collection */
        $collection = $this->dataProvider->getCollection($resourceClass, $operationName, $context);

        foreach ($collection as $index=>$route) {
            if (!$this->security->isGranted(RouteVoter::ROUTE_READ, $route)) {
                $collection->getIterator()
                $collection->removeElement($route);
            }
        }
        return $collection;
    }
}
