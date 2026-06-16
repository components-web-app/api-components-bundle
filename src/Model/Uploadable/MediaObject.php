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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Ramsey\Uuid\Uuid;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource(
    types: ['http://schema.org/MediaObject'],
    normalizationContext: ['jsonld_embed_context' => true],
    operations: [new Get()]
)]
final class MediaObject
{
    #[ApiProperty(readable: false, writable: false, identifier: true)]
    private string $id;

    #[ApiProperty(writable: false, types: ['http://schema.org/contentUrl'])]
    public string $contentUrl;

    #[ApiProperty(writable: false)]
    public int $fileSize;

    #[ApiProperty(writable: false, types: ['http://schema.org/encodingFormat'])]
    public string $mimeType;

    #[ApiProperty(writable: false, types: ['http://schema.org/width'])]
    public ?int $width = null;

    #[ApiProperty(writable: false, types: ['http://schema.org/height'])]
    public ?int $height = null;

    #[ApiProperty(writable: false)]
    public ?string $imagineFilter = null;

    // defined otherwise the IRI mapping in API Platform does not work with just the getter method
    #[ApiProperty(writable: false, types: ['http://schema.org/contentSize'])]
    private ?string $formattedFileSize = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFormattedFileSize(): string
    {
        return $this->formattedFileSize ?? $this->fileSize < 0 ? '' : $this->convertSizeToString($this->fileSize);
    }

    private function convertSizeToString(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . 'GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . 'MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . 'KB';
        }

        return $bytes . 'B';
    }
}
