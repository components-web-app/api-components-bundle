<?php

namespace Silverback\ApiComponentBundle\Form\Handler;

interface ContextProviderInterface
{
    public function getContext(): ?array;
}
