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
trait UploadableTrait
{
    private Collection $media;

    public function getMedia(): Collection
    {
        return $this->media;
    }

    /**
     * @return static
     */
    public function setMedia(Collection $media)
    {
        $this->media = $media;

        return $this;
    }
}
