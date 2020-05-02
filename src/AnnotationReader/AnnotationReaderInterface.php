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

namespace Silverback\ApiComponentsBundle\AnnotationReader;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 * @author Daniel West <daniel@silverback.is>
 */
interface AnnotationReaderInterface
{
    public function getConfiguration($class);

    /**
     * @param object|string $class
     */
    public function isConfigured($class): bool;
}
