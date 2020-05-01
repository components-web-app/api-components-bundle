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

namespace Silverback\ApiComponentsBundle\Entity\Utility;

/**
 * @author Daniel West <daniel@silverback.is>
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
trait UploadableTrait
{
    private ?string $filename = null;

    private ?array $filesInfo = null;

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * @return static
     */
    public function setFilename(string $filename)
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilesInfo(): ?array
    {
        return $this->filesInfo;
    }

    /**
     * @return static
     */
    public function setFilesInfo(?array $filesInfo)
    {
        $this->filesInfo = $filesInfo;

        return $this;
    }
}
