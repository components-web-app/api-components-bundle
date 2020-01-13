<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\MappedSuperclass()
 */
abstract class AbstractPage implements TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", inversedBy="pageTemplate", cascade={"persist"})
     * @var Collection|Route[]
     */
    public Collection $routes;

    public function __construct()
    {
        $this->setId();
        $this->routes = new ArrayCollection();
    }
}
