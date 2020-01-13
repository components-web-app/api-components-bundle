<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Utility;

use DateTimeImmutable;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface TimestampedInterface
{
    public function setCreated(DateTimeImmutable $created);
    public function getCreated(): ?DateTimeImmutable;
}
