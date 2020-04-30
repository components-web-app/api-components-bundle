<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Annotation;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 *
 * @Annotation
 * @Target("CLASS")
 */
final class Publishable
{
    public string $fieldName = 'publishedAt';

    public ?string $isGranted = null;

    public string $associationName = 'publishedResource';

    public string $reverseAssociationName = 'draftResource';

    /**
     * @var string[]
     */
    public ?array $validationGroups = null;
}
