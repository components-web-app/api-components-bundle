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

namespace Silverback\ApiComponentsBundle\Security\Voter;

use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class SiteConfigParameterVoter extends Voter
{
    public const NAME = 'read_site_config';

    public function __construct(private readonly string $permission, private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        return self::NAME === $attribute && $subject instanceof SiteConfigParameter && $this->permission;
    }

    /**
     * @param Route $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        return $this->isGranted();
    }

    private function isGranted(): bool
    {
        try {
            return $this->authorizationChecker->isGranted(new Expression($this->permission));
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        }
    }
}
