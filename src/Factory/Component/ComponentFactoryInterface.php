<?php

namespace Silverback\ApiComponentBundle\Factory\Component;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;

interface ComponentFactoryInterface
{
    public function getComponent(): AbstractComponent;
    public function create(AbstractContent $owner, ?array $ops = null): AbstractComponent;
    public function processOps(?array $ops): array;
    public static function defaultOps (): array;
}
