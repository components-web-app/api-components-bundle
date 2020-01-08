<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\MappedSuperclass
 */
abstract class AbstractComponent implements ComponentInterface, TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;
    use UiTrait;

    public function __construct()
    {
        $this->setId();
    }
}
