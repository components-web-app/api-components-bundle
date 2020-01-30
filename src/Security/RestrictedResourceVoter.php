<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Security;

use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\RestrictedResourceInterface;
use Symfony\Component\Security\Core\Security;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RestrictedResourceVoter
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    private function rolesVote(array $roles): bool
    {
        if (!count($roles)) {
            return true;
        }
        $negativeRoles = [];
        $positiveRoles = [];
        foreach ($roles as $role) {
            if (strpos($role, '!') === 0) {
                $negativeRoles[] = substr($role, 1);
                continue;
            }
            $positiveRoles[] = $role;
        }
        $positivePass = count($positiveRoles) && $this->security->isGranted($positiveRoles);
        $negativePass = count($negativeRoles) && !$this->security->isGranted($negativeRoles);
        return $positivePass || $negativePass;
    }

    public function isSupported($data): ?RestrictedResourceInterface
    {
        if ($data instanceof RestrictedResourceInterface && !$data instanceof AbstractContent) {
            return $data;
        }
        return null;
    }

    public function vote($object): bool
    {
        return (!($restrictedResource = $this->isSupported($object)) ||
            !($roles = $restrictedResource->getSecurityRoles()) === null ||
            $this->rolesVote($roles))
        ;
    }
}
