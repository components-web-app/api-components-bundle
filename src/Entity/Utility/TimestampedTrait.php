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

use DateTime;
use DateTimeImmutable;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait TimestampedTrait
{
    private ?DateTimeImmutable $createdAt = null;

    public ?DateTime $modifiedAt = null;

    /** @return static */
    public function setCreatedAt(DateTimeImmutable $createdAt)
    {
        if (!$this->createdAt) {
            $this->createdAt = $createdAt;
        }

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return static */
    public function setModifiedAt(DateTime $modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getModifiedAt(): ?DateTime
    {
        return $this->modifiedAt;
    }
}
