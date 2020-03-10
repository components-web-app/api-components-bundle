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

use DateTime;
use DateTimeImmutable;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface TimestampedInterface
{
    public function setCreated(DateTimeImmutable $created);

    public function getCreated(): ?DateTimeImmutable;

    public function setModified(DateTime $modified);

    public function getModified(): ?DateTime;
}
