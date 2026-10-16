<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator;

use League\Flysystem\FilesystemException;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;

/**
 * @internal
 */
interface StoredFileExport
{
    /**
     * @throws FilesystemException when the stored file cannot be read
     */
    public function expression(string $adapter, string $storedPath, string $field, ImportCollector $imports): string;

    public function count(): int;
}
