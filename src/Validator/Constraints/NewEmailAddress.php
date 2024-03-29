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
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class NewEmailAddress extends Constraint
{
    public string $message = 'Your new email address should be different.';
    public string $uniqueMessage = 'Someone else is already registered with that email address.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
