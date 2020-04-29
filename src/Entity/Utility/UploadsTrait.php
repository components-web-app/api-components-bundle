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

use Doctrine\Common\Collections\Collection;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait UploadsTrait
{
    private Collection $files;

    public function getFiles(): Collection
    {
        return $this->files;
    }

    /**
     * @return static
     */
    public function setFiles(Collection $files)
    {
        $this->files = $files;

        return $this;
    }
}
