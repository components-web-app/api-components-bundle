<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\DataTransformer;

interface DataTransformerInterface
{
    public function transform($object, array $context = []);

    public function supportsTransformation($data, array $context = []): bool;
}
