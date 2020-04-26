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

namespace Silverback\ApiComponentBundle\Annotation;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Annotation
 * @Target("CLASS")
 */
final class Timestamped
{
    public string $createdAtField = 'createdAt';

    public string $modifiedAtField = 'modifiedAt';
}
