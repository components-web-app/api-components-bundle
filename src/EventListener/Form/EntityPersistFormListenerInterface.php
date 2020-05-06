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

namespace Silverback\ApiComponentsBundle\EventListener\Form;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedHelper;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface EntityPersistFormListenerInterface
{
    public function init(ManagerRegistry $registry, TimestampedAnnotationReader $timestampedAnnotationReader, TimestampedHelper $timestampedHelper): void;
}
