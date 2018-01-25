<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;

interface ComponentInterface
{
    public function getComponent(): Component;
    public function create($owner, ?array $ops = null): Component;
    public function processOps(?array $ops): array;
    public static function defaultOps (): array;
}
