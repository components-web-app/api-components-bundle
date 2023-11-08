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

namespace Silverback\ApiComponentsBundle\Annotation;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Timestamped
{
    public string $createdAtField;

    public string $modifiedAtField;

    public function __construct(string $createdAtField = 'createdAt', string $modifiedAtField = 'modifiedAt')
    {
        $this->createdAtField = $createdAtField;
        $this->modifiedAtField = $modifiedAtField;
    }
}
