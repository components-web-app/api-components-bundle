<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Security\Voter;

use Silverback\ApiComponentsBundle\ApiResource\ResourceManifest;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResourceManifestVoter extends Voter
{
    public const READ_MANIFEST = 'read_manifest';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::READ_MANIFEST === $attribute && $subject instanceof ResourceManifest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $entity = $subject->entity;

        if ($entity instanceof Route) {
            return $this->security->isGranted(RouteVoter::READ_ROUTE, $entity);
        }

        if ($entity instanceof RoutableInterface) {
            return $this->security->isGranted(AbstractRoutableVoter::READ_ROUTABLE, $entity);
        }

        return false;
    }
}
