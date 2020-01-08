<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Utility;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

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
