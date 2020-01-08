<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\SortableInterface;
use Silverback\ApiComponentBundle\Entity\Utility\SortableTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="layout", inversedBy="pageData")
 * })
 */
class PageData extends AbstractPage implements SortableInterface
{
    use SortableTrait;
    /*
     * Extend this class for pages where the same page template should be used for multiple entities.
     * A good example is an article page. You would create an Article entity in your project that extends this class.
     * That article can then be accessed via a route on the API and the data in this class will override whatever is in the template.
     * It allows you to redefine component groups using the same name as used in the template
     * The variables
     */

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentGroup", mappedBy="pageData")
     * @var Collection|ComponentGroup[]
     */
    public Collection $componentGroups;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", mappedBy="pageData", cascade={"persist"})
     * @var Collection|Route[]
     */
    public Collection $routes;

    public function getSortCollection(): ?Collection
    {
        return null;
    }
}
