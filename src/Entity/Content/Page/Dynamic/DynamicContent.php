<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\Page\DynamicPage;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorMap({
 *     "article_page" = "Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\ArticlePage\ArticlePage"
 * })
 */
abstract class DynamicContent extends DynamicContentBase
{
    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", mappedBy="dynamicContent", cascade={"persist"})
     */
    protected $routes;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=36)
     * @var string
     */
    protected $id;

    /**
     * @var DynamicPage|null
     * @Groups({"content_read", "route_read", "component_read"})
     */
    private $dynamicPage;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex();
        $this->title = 'New dynamic page';
        $this->routes = new ArrayCollection;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDynamicPage(): ?DynamicPage
    {
        return $this->dynamicPage;
    }

    public function setDynamicPage(?DynamicPage $dynamicPage): void
    {
        $this->dynamicPage = $dynamicPage;
    }

    public function getParentRoute(): ?Route
    {
        return null;
    }

    public function getSortCollection(): ?Collection
    {
        return null;
    }
}
