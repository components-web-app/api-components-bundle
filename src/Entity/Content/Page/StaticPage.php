<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content\Page;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 */
class StaticPage extends AbstractPage implements RouteAwareInterface
{
    use RouteAwareTrait;

    /**
     * Declared here for groups differ from dynamic
     * @Groups({"default"})
     */
    protected $componentLocations;

    public function __construct()
    {
        parent::__construct();
        $this->routes = new ArrayCollection;
    }

    /**
     * @ApiProperty()
     * @Groups({"content","route"})
     */
    public function isDynamic(): bool
    {
        return false;
    }
}
