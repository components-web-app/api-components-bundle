<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Utility;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait TimestampedTrait
{
    /** @ORM\Column(type="date_immutable") */
    private DateTimeImmutable $created;

    /** @ORM\Column(type="datetime") */
    public DateTime $modified;

    /** @return static */
    public function setCreated(DateTimeImmutable $created)
    {
        if (!$this->created) {
            $this->created = $created;
        }
        return $this;
    }

    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }
}
