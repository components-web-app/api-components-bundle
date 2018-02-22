<?php

namespace Silverback\ApiComponentBundle\Entity;

interface DeleteCascadeInterface
{
    /**
     * @return bool
     */
    public function onDeleteCascade(): bool;
}
