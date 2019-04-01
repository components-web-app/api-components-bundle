<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content\Page;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Route\ChildRouteInterface;
use Silverback\ApiComponentBundle\Entity\Route\ChildRouteTrait;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @ORM\Entity()
 */
class DynamicPage extends AbstractPage implements SortableInterface, ChildRouteInterface
{
    use SortableTrait;
    use ChildRouteTrait;

    /**
     * Groups differ from page
     * @Groups({"dynamic_content", "route"})
     */
    protected $componentLocations;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"component_write"})
     * @var null|string
     */
    protected $dynamicPageClass;

    protected $dynamic = true;

    public function getDynamicPageClass(): ?string
    {
        return $this->dynamicPageClass;
    }

    public function setDynamicPageClass(?string $dynamicPageClass): self
    {
        $this->dynamicPageClass = $dynamicPageClass;
        return $this;
    }

    public function getSortCollection(): ?Collection
    {
        return null;
    }


    /**
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context): void
    {
        if (!$this->getDynamicPageClass()) {
            $context->buildViolation('The content is required if dynamicPageClass is not provided')
                ->atPath('content')
                ->addViolation();
        }
    }
}
