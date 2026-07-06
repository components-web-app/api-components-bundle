<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Annotation;

/**
 * Marks a component TYPE as opt-in only: it may be placed in a ComponentGroup ONLY when that group's
 * allowedComponents explicitly lists its collection IRI. Everywhere else it is rejected on save by
 * ComponentPositionValidator, and hidden from the admin add dialog — the front-end reads the per-type
 * `explicitAllowOnly` flag surfaced on the Hydra API docs `supportedClass` entry
 * (see VersionedDocumentationNormalizer).
 *
 * Replaces the former AbstractComponent::isPositionRestricted() per-instance method.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ExplicitAllowOnly
{
}
