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

use ApiPlatform\State\SerializerContextBuilderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ComponentPositionContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;
    private RoleHierarchyInterface $roleHierarchy;
    private Security $security;

    public function __construct(SerializerContextBuilderInterface $decorated, RoleHierarchyInterface $roleHierarchy, Security $security)
    {
        $this->decorated = $decorated;
        $this->roleHierarchy = $roleHierarchy;
        $this->security = $security;
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        if (ComponentPosition::class !== $context['resource_class']) {
            return $context;
        }
        $rw = $normalization ? 'read' : 'write';
        $context['groups'][] = sprintf('ComponentPosition:%s', $rw);
        $user = $this->security->getUser();
        if ($user) {
            $reachableRoles = $this->roleHierarchy->getReachableRoleNames($user->getRoles());
            foreach ($reachableRoles as $reachableRole) {
                $context['groups'][] = sprintf('ComponentPosition:%s:%s', $rw, strtolower($reachableRole));
            }
        }

        return $context;
    }
}
