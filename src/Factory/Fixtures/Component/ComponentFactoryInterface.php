<?php

namespace Silverback\ApiComponentBundle\Factory\Fixtures\Component;

use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;

interface ComponentFactoryInterface
{
    public function create(?array $ops = null, ?AbstractContent $owner = null);
}
