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

namespace Silverback\ApiComponentBundle\Model\File;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @ApiResource(
 *     iri="http://schema.org/MediaObject"
 * )
 */
class MediaObject
{
    /**
     * @ApiProperty(iri="http://schema.org/contentUrl")
     */
    private string $contentUrl;

    private int $fileSize;

    private string $mimeType;

    private ?ImageDimensions $dimensions;

    private ?string $imagineFilter = null;
}
