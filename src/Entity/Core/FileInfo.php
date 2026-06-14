<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Entity\Core;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * Optional entity only mapped if imagine installed.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'imagine_cached_file_metadata',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'unique_cache_item', columns: ['path', 'filter'])]
)]
class FileInfo
{
    use IdTrait;

    #[ORM\Column]
    public string $path;

    #[ORM\Column(name: 'mime_type')]
    public string $mimeType;

    #[ORM\Column(name: 'file_size', type: 'integer')]
    public int $fileSize;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $width;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $height;

    #[ORM\Column(nullable: true)]
    public ?string $filter;

    public function __construct(string $path, string $mimeType, int $fileSize, ?int $width, ?int $height, ?string $filter = null)
    {
        $this->path = $path;
        $this->mimeType = $mimeType;
        $this->width = $width;
        $this->height = $height;
        $this->fileSize = $fileSize;
        $this->filter = $filter;
    }
}
