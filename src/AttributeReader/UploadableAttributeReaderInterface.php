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

namespace Silverback\ApiComponentsBundle\AttributeReader;

use Silverback\ApiComponentsBundle\Annotation\Uploadable;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;

/**
 * @author Daniel West <daniel@silveback.is>
 */
interface UploadableAttributeReaderInterface extends AttributeReaderInterface
{
    public function getConfiguration($class): Uploadable;

    public function isFieldConfigured(\ReflectionProperty $property): bool;

    public function getPropertyConfiguration(\ReflectionProperty $property): UploadableField;

    public function getConfiguredProperties($data, bool $skipUploadableCheck = false): iterable;
}
