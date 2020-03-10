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
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait TimestampedTrait
{
    /**
     * @ORM\Column(type="date_immutable")
     * @ApiProperty(writable=false)
     * @Assert\NotNull()
     */
    private ?DateTimeImmutable $created = null;

    /**
     * @ORM\Column(type="datetime")
     * @ApiProperty(writable=false)
     * @Assert\NotNull()
     */
    public ?DateTime $modified = null;

    /** @return static */
    public function setCreated(DateTimeImmutable $created)
    {
        if (!$this->created) {
            $this->created = $created;
        }

        return $this;
    }

    public function getCreated(): ?DateTimeImmutable
    {
        return $this->created;
    }

    /** @return static */
    public function setModified(DateTime $modified)
    {
        $this->modified = $modified;

        return $this;
    }

    public function getModified(): ?DateTime
    {
        return $this->modified;
    }
}
