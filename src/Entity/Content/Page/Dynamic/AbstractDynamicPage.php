<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Page\AbstractPage;
use Silverback\ApiComponentBundle\Entity\PublishableInterface;
use Silverback\ApiComponentBundle\Entity\PublishableTrait;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractDynamicPage
 * @package Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic
 * @ORM\Entity()
 */
abstract class AbstractDynamicPage extends AbstractPage implements SortableInterface, PublishableInterface
{
    use SortableTrait;
    use PublishableTrait;

    /**
     * @Groups({"dynamic_content", "route"})
     */
    protected $componentLocations;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route")
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id", onDelete="SET NULL")
     * @var Route|null
     */
    protected $parentRoute;

    /**
     * @ORM\Column(type="boolean", options={"default":"0"})
     * @var boolean
     */
    protected $nested = false;

    /**
     * @param null|Route $parentRoute
     * @return static
     */
    public function setParentRoute(?Route $parentRoute)
    {
        $this->parentRoute = $parentRoute;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getParentRoute(): ?Route
    {
        if ($this->parentRoute) {
            return $this->parentRoute;
        }
        if ($this->nested) {
            return parent::getParentRoute();
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isNested(): bool
    {
        return $this->nested;
    }

    /**
     * @param bool $nested
     * @return static
     */
    public function setNested(bool $nested)
    {
        $this->nested = $nested;
        return $this;
    }

    /**
     * @ApiProperty()
     * @Groups({"content","route"})
     */
    public function isDynamic(): bool
    {
        return true;
    }

    public function getSortCollection(): ?Collection
    {
        return null;
    }
}
