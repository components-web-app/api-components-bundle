<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

interface SortableInterface
{
    public function getSort(): ?int;
    public function setSort(?int $sortOrder): void;
}
