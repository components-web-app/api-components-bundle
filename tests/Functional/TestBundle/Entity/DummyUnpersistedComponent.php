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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Ramsey\Uuid\Uuid;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource]
class DummyUnpersistedComponent
{
    #[ApiProperty(identifier: true)]
    private string $id;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex()->toString();
    }

    #[ApiProperty(readable: false)]
    public function getId(): string
    {
        return $this->id;
    }
}
