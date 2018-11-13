<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity;

interface DeleteCascadeInterface
{
    /**
     * @return bool
     */
    public function onDeleteCascade(): bool;
}
