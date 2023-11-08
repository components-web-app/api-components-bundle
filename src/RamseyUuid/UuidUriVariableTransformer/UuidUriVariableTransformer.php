<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\RamseyUuid\UuidUriVariableTransformer;

use ApiPlatform\Exception\InvalidUriVariableException as LegacyInvalidUriVariableException;
use ApiPlatform\Metadata\Exception\InvalidUriVariableException;
use ApiPlatform\Metadata\UriVariableTransformerInterface;
use ApiPlatform\RamseyUuid\UriVariableTransformer\UuidUriVariableTransformer as BaseUuidUriVariableTransformer;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UuidUriVariableTransformer implements UriVariableTransformerInterface
{
    private BaseUuidUriVariableTransformer $decorated;

    public function __construct(BaseUuidUriVariableTransformer $decorated)
    {
        $this->decorated = $decorated;
    }

    public function transform($value, array $types, array $context = [])
    {
        try {
            return $this->decorated->transform($value, $types, $context);
        } catch (InvalidUriVariableException|LegacyInvalidUriVariableException $exception) {
            return $value;
        }
    }

    public function supportsTransformation($value, array $types, array $context = []): bool
    {
        return $this->decorated->supportsTransformation($value, $types, $context);
    }
}
