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

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ComponentPosition extends Constraint
{
    public string $message = 'The IRI `{{ iri }}` is not permitted to be added to the collection `{{ reference }}`. Allowed IRIs: {{ allowed }}';
    public string $restrictedMessage = 'The IRI `{{ iri }}` must be specifically allowed within the collection {{ reference }}';
    public string $invalidPageDataClassMessage = '`{{ class }}` is not a known PageData resource class';
    public string $fullMessage = 'The component group `{{ reference }}` is full: it holds at most {{ limit }} component.|The component group `{{ reference }}` is full: it holds at most {{ limit }} components.';
    public string $anchorNotInGroupMessage = 'The position `{{ iri }}` is not in the component group `{{ reference }}`.';
    public string $anchorWithSortValueMessage = 'Send an anchor or a sortValue, not both: the server chooses the sortValue.';
    public string $bothAnchorsMessage = 'Send either after or before, not both.';
    public string $cycleMessage = 'The component cannot be placed in the component group `{{ reference }}`: the group is inside the component.';
    public string $selfAnchorMessage = 'A position cannot be placed relative to itself.';
    public string $invalidPageDataPropertyMessage = '`{{ property }}` is not a component-typed property on `{{ class }}`';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
