<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Utility;

use Doctrine\ORM\Mapping as ORM;

trait UiTrait
{
    /** @ORM\Column(nullable=true) */
    public ?string $uiComponent;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $uiClassNames;
}
