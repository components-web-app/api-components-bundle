<?php

namespace Silverback\ApiComponentBundle\Factory\Entity;

interface FactoryInterface
{
    public function create(?array $ops = null);
}
