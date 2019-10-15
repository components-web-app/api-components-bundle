<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

trait RestrictedResourceTrait
{
    /**
     * @ORM\Column(type="json_array", nullable=true)
     * @var array|null
     */
    protected $securityRoles;

    public function getSecurityRoles(): ?array
    {
        return $this->securityRoles;
    }

    /**
     * @param string $securityRole
     * @return static
     */
    public function addSecurityRole(string $securityRole)
    {
        $this->securityRoles[] = $securityRole;
        return $this;
    }

    /**
     * @param array|null $securityRoles
     * @return static
     */
    public function setSecurityRoles(?array $securityRoles)
    {
        if (!$securityRoles) {
            $this->securityRoles = $securityRoles;
            return $this;
        }
        $this->securityRoles = [];
        foreach ($securityRoles as $securityRole) {
            $this->addSecurityRole($securityRole);
        }
        return $this;
    }
}
