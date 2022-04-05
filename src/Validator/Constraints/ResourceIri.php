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

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @author Daniel West <daniel@silverback.is>
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ResourceIri extends Constraint
{
    public string $message = '{{ value }} is not a valid IRI';
}
