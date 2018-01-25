<?php

namespace Silverback\ApiComponentBundle\DataFixtures;

interface ComponentAwareInterface
{
    public function __construct(ComponentServiceLocator $serviceLocator);
}
