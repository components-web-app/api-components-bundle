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

use Silverback\ApiComponentBundle\Annotation as Silverback;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
trait UploadableTrait
{
    private ?string $fileName = null;

    /**
     * @Silverback\UploadableField
     */
    private ?UploadedFile $file = null;

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getFile(): ?UploadedFile
    {
        return $this->file;
    }

    public function setFile(?UploadedFile $file): self
    {
        $this->file = $file;

        return $this;
    }
}
