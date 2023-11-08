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

namespace Silverback\ApiComponentsBundle\Annotation;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Publishable
{
    public string $fieldName;

    public ?string $isGranted;

    public string $associationName;

    public string $reverseAssociationName;

    /**
     * @var string[]
     */
    public ?array $validationGroups;

    public function __construct(string $fieldName = 'publishedAt', string $isGranted = null, string $associationName = 'publishedResource', string $reverseAssociationName = 'draftResource', array $validationGroups = null)
    {
        $this->fieldName = $fieldName;
        $this->isGranted = $isGranted;
        $this->associationName = $associationName;
        $this->reverseAssociationName = $reverseAssociationName;
        $this->validationGroups = $validationGroups;
    }
}
