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

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped(createdAtField="customCreatedAt", modifiedAtField="customModifiedAt")
 * @ApiResource
 * @ORM\Entity
 */
class DummyCustomTimestamped
{
    use IdTrait;

    private ?DateTimeImmutable $customCreatedAt = null;

    public ?DateTime $customModifiedAt = null;

    public function __construct()
    {
        $this->setId();
    }

    public function setCustomCreatedAt(DateTimeImmutable $customCreatedAt): self
    {
        if (!$this->customCreatedAt) {
            $this->customCreatedAt = $customCreatedAt;
        }

        return $this;
    }

    public function getCustomCreatedAt(): ?DateTimeImmutable
    {
        return $this->customCreatedAt;
    }

    public function setCustomModifiedAt(DateTime $customModifiedAt): self
    {
        $this->customModifiedAt = $customModifiedAt;

        return $this;
    }

    public function getCustomModifiedAt(): ?DateTime
    {
        return $this->customModifiedAt;
    }
}
