<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Serializer\ContextBuilder;

use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\CwaResourceLoader;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class CwaResourceContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilderInterface $decorated,
        private RoleHierarchyInterface $roleHierarchy,
        private Security $security,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        if (
            !is_a($resourceClass = $context['resource_class'], AbstractComponent::class, true) &&
            !is_a($resourceClass, AbstractPageData::class, true)
        ) {
            return $context;
        }

        $reflectionClass = new \ReflectionClass($resourceClass);
        $shortName = $reflectionClass->getShortName();
        $componentNames = [$shortName];
        while ($parent = $reflectionClass->getParentClass()) {
            $componentNames[] = $parent->getShortName();
            $reflectionClass = $parent;
        }
        $rw = $normalization ? 'read' : 'write';
        foreach ($componentNames as $componentName) {
            $context['groups'][] = sprintf('%s:%s:%s', $componentName, CwaResourceLoader::GROUP_NAME, $rw);
        }

        $user = $this->security->getUser();
        if ($user) {
            $reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());
            foreach ($reachableRoles as $reachableRole) {
                $context['groups'][] = sprintf('%s:%s:%s:%s', $shortName, CwaResourceLoader::GROUP_NAME, $rw, strtolower($reachableRole));
            }
        }

        return $context;
    }
}
