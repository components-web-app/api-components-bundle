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

namespace Silverback\ApiComponentsBundle\Model\Uploadable;

use Ramsey\Uuid\Uuid;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class MediaObject
{
    private string $id;

    public string $contentUrl;

    public int $fileSize;

    public string $mimeType;

    public ?int $width = null;

    public ?int $height = null;

    public ?string $imagineFilter = null;

    // defined otherwise the IRI mapping in API Platform does not work with just the getter method
    private string $formattedFileSize = '';

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex()->toString();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getFormattedFileSize(): string
    {
        return $this->fileSize < 0 ? '' : $this->convertSizeToString($this->fileSize);
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
