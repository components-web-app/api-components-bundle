<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedFile;

final class StoredFileNameMatcher
{
    private const string TOKENISED = '/\A[a-z0-9][a-z0-9-]{0,99}-[0-9a-f]{8}(?:\.[a-z0-9]+)?\z/';
    private const string DATA_URI_UUID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.[A-Za-z0-9]*\z/';

    public function matches(string $path): bool
    {
        $slash = strrpos($path, '/');
        $basename = false === $slash ? $path : substr($path, $slash + 1);

        return 1 === preg_match(self::TOKENISED, $basename) || 1 === preg_match(self::DATA_URI_UUID, $basename);
    }
}
