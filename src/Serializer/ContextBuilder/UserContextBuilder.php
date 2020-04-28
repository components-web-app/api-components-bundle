<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Serializer\ContextBuilder;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class UserContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;
    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(SerializerContextBuilderInterface $decorated, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->decorated = $decorated;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        $resourceClass = @$context['resource_class'] ?? null;
        if (!is_subclass_of($resourceClass, AbstractUser::class)) {
            return $context;
        }

        $serializerGroupsConfigured = isset($context['groups']) && \is_array($context['groups']);
        if (!$serializerGroupsConfigured) {
            $context['groups'] = [];
        }
        $postfix = false === $normalization ? 'input' : 'output';
        $context['groups'][] = sprintf('User:%s', $postfix);

        if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN')) {
            $context['groups'][] = 'User:super_admin';
        }

        return $context;
    }
}
