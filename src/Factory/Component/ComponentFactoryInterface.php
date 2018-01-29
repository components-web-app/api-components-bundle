<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

interface ComponentFactoryInterface
{
    public function getComponent(): AbstractComponent;
    public function create($owner, ?array $ops = null): AbstractComponent;
    public function processOps(?array $ops): array;
    public static function defaultOps (): array;
}
