<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Security\Voter\ComponentVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<object>
 *
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class DenyAccessStateProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private ProviderInterface $decorated,
        private Security $security,
        private RouteRepository $routeRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $data = $this->decorated->provide($operation, $uriVariables, $context);

        if ($data instanceof AbstractComponent && !$this->security->isGranted(ComponentVoter::READ_COMPONENT, $data)) {
            throw new AccessDeniedException('Component access denied.');
        }

        if ($data instanceof AbstractPageData && !$this->isPageDataAllowedByRoute($data)) {
            throw new AccessDeniedException('Page data access denied.');
        }

        return $data;
    }

    private function isPageDataAllowedByRoute(AbstractPageData $pageData): bool
    {
        $routes = $this->routeRepository->findByPageData($pageData);
        if ([] === $routes) {
            return true;
        }

        foreach ($routes as $route) {
            if ($this->security->isGranted(RouteVoter::READ_ROUTE, $route)) {
                return true;
            }
        }

        return false;
    }
}
