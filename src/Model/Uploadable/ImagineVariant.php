<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Model\Uploadable;

final readonly class ImagineVariant
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
        public string $field,
        public string $path,
        public string $filter,
        public ImagineVariantStatus $status,
    ) {
    }
}
