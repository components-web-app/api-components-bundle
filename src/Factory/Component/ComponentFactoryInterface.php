<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;

interface ComponentFactoryInterface
{
    public function getComponent(): Component;
    public function create(AbstractContent $owner, ?array $ops = null): Component;
    public function processOps(?array $ops): array;
    public static function defaultOps(): array;
}
