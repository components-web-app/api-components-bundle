<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DataTransformer;

interface SortableDataTransformerInterface extends DataTransformerInterface
{
    public function getSortOrder(): int;
}
