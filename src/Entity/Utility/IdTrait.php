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

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @internal
 */
trait IdTrait
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=36)
     */
    private string $id;

    private function setId(): void
    {
        $this->id = Uuid::uuid4()->getHex();
    }

    public function getId(): string
    {
        return $this->id;
    }
}
