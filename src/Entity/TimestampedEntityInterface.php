<?php

namespace Silverback\ApiComponentBundle\Entity;

use DateTime;
use DateTimeImmutable;

interface TimestampedEntityInterface
{
    /**
     * @return DateTimeImmutable
     */
    public function getCreated(): DateTimeImmutable;

    /**
     * @param DateTimeImmutable $created
     * @return static
     */
    public function setCreated(DateTimeImmutable $created);

    /**
     * @return DateTime
     */
    public function getModified(): DateTime;

    /**
     * @param DateTime $modified
     * @return static
     */
    public function setModified(DateTime $modified);
}
