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

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ComponentUiAllowedInGroups extends Constraint
{
    public string $uiMessage = 'The UI `{{ ui }}` is not allowed for `{{ iri }}` in the component group `{{ reference }}`.';
    public string $stylesMessage = 'The styles `{{ styles }}` are not allowed for `{{ iri }}` in the component group `{{ reference }}`.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
