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

namespace Silverback\ApiComponentsBundle\Metadata\Factory;

use Silverback\ApiComponentsBundle\Exception\PageDataNotFoundException;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface PageDataMetadataFactoryInterface
{
    /**
     * Creates a page data metadata.
     *
     * @throws PageDataNotFoundException
     */
    public function create(string $resourceClass): PageDataMetadata;
}
