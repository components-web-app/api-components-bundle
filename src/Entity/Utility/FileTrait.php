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

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Model\File\MediaObject;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource
 * @ORM\Entity
 */
trait FileTrait
{
    use TimestampedTrait;

    /**
     * @Assert\NotNull(groups={"File:write"})
     */
    private ?File $file = null;

    private string $filePath;

    private ?object $uploadsResource = null;

    /** @var Collection|MediaObject[] */
    private Collection $mediaObjects;

    public function getFile(): ?File
    {
        return $this->file;
    }

    /**
     * @return static
     */
    public function setFile(?File $file)
    {
        $this->file = $file;

        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return static
     */
    public function setFilePath(string $filePath)
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getUploadsResource(): ?object
    {
        return $this->uploadsResource;
    }

    /**
     * @return static
     */
    public function setUploadsResource(?object $uploadsResource)
    {
        $this->uploadsResource = $uploadsResource;

        return $this;
    }

    public function getMediaObjects(): Collection
    {
        return $this->mediaObjects;
    }

    /**
     * @return static
     */
    public function setMediaObjects(Collection $mediaObjects)
    {
        $this->mediaObjects = $mediaObjects;

        return $this;
    }
}
