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

namespace Silverback\ApiComponentBundle\Serializer;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

class AdminContextBuilder implements SerializerContextBuilderInterface
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
        // api_empty_resource_as_iri - investigate usage with serialization groups and max depth. We want all properties serialized as usual but with the additional admin/super_admin groups if permitted
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
//        $context[AbstractObjectNormalizer::ENABLE_MAX_DEPTH] = true;
//        if (!isset($context['groups'])) {
//            $context['groups'] = ['Default'];
//        }
//        if (isset($context['groups'])) {
//            if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
//                $context['groups'][] = 'admin';
//            }
//            if ($this->authorizationChecker->isGranted('ROLE_SUPER_ADMIN')) {
//                $context['groups'][] = 'super_admin';
//            }
//        }

        return $context;
    }
}
