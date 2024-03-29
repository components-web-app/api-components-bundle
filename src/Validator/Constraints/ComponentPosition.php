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
 *
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ComponentPosition extends Constraint
{
    public string $message = 'The IRI `{{ iri }}` is not permitted to be added to the collection `{{ reference }}`. Allowed IRIs: {{ allowed }}';
    public string $restrictedMessage = 'The IRI `{{ iri }}` must be specifically allowed within the collection {{ reference }}';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
