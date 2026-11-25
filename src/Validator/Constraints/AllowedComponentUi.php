<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class AllowedComponentUi extends Constraint
{
    public string $keyMessage = '`{{ key }}` is not the collection IRI of a component resource.';
    public string $ruleMessage = 'The rule for `{{ key }}` must be an object with `ui`, `styles` or both, and nothing else.';
    public string $uiMessage = 'The `ui` for `{{ key }}` must be a non-empty list of strings or nulls.';
    public string $stylesMessage = 'The `styles` for `{{ key }}` must be a non-empty list of strings.';
}
