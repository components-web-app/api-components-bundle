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

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Form\AbstractType;
use Silverback\ApiComponentBundle\Form\FormTypeInterface;
use Symfony\Component\Validator\Constraint;

/**
 * @author Daniel West <daniel@silverback.is>
 * @Annotation
 */
class FormTypeClass extends Constraint
{
    public string $message;

    public function __construct($options = null)
    {
        $conditionsStr = vsprintf(
            'It should extend %s, implement %s or tagged %s',
            [
                AbstractType::class,
                FormTypeInterface::class,
                'silverback_api_component.form_type',
            ]
        );
        $this->message = 'The string "{{ string }}" does not refer to a class configured correctly as a form type. ' . $conditionsStr;

        parent::__construct($options);
    }
}
