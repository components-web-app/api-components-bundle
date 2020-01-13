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

namespace Silverback\ApiComponentBundle\Entity\Utility;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Dto\File\FileData;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait FileTrait
{
    /**
     * @ORM\Column(type="string", nullable=true)
     * @ApiProperty(iri="http://schema.org/contentUrl")
     */
    protected ?string $filePath;

    public ?FileData $fileData;

    public static function getImagineFilters(): array
    {
        return [
            'thumbnail' => 'thumbnail',
            'placeholderSquare' => 'placeholder_square',
            'placeholder' => 'placeholder',
        ];
    }

    public function getDirectory(): ?string
    {
        return null;
    }
}
