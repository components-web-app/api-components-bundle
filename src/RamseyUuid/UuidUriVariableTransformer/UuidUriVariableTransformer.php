<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\RamseyUuid\UuidUriVariableTransformer;

use ApiPlatform\Metadata\Exception\InvalidUriVariableException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UriVariableTransformerInterface;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class UuidUriVariableTransformer implements UriVariableTransformerInterface
{
    private const array ACCEPTS_ANY_IDENTIFIER = [Route::class, ComponentGroup::class, Form::class];

    public function __construct(private readonly UriVariableTransformerInterface $decorated)
    {
    }

    public function transform(mixed $value, array $types, array $context = []): mixed
    {
        try {
            return $this->decorated->transform($value, $types, $context);
        } catch (InvalidUriVariableException $exception) {
            $operation = $context['operation'] ?? null;
            if ($operation instanceof Operation && \in_array($operation->getClass(), self::ACCEPTS_ANY_IDENTIFIER, true)) {
                return $value;
            }

            throw $exception;
        }
    }

    public function supportsTransformation(mixed $value, array $types, array $context = []): bool
    {
        return $this->decorated->supportsTransformation($value, $types, $context);
    }
}
