<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * A #[Timestamped] resource that does NOT use TimestampedTrait.
 *
 * TimestampedTrait::setCreatedAt() silently ignores the new value once createdAt is set, which
 * incidentally shields resources using the trait from a client-supplied createdAt. Nothing requires
 * an application entity to use the trait or to write a guarded setter, so this entity exposes plain
 * public properties — the shape the bundle must protect on its own.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[Silverback\Timestamped]
#[ApiResource]
#[ORM\Entity]
class DummyUnguardedTimestamped
{
    use IdTrait;

    public ?\DateTimeImmutable $createdAt = null;

    public ?\DateTime $modifiedAt = null;
}
