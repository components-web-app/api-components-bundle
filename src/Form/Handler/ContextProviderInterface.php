<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Form\Handler;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface ContextProviderInterface
{
    public function getContext(): ?array;
}
