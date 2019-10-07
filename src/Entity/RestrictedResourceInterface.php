<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity;

interface RestrictedResourceInterface
{
    public function getSecurityRoles(): ?array;

    public function addSecurityRole(string $securityRole);

    public function setSecurityRoles(?array $securityRoles);
}
