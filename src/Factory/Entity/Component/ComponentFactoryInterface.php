<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Component;

interface ComponentFactoryInterface
{
    public function create(?array $ops = null);
}
