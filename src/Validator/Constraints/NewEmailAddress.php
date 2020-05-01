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
class NewEmailAddress extends Constraint
{
    public string $differentMessage = 'Your new username should be different';
    public string $uniqueMessage = 'Someone else is already registered with that email address';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
