<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Although a user will be able to get the routes and the tree of data down to getting the ID for a component
 * fetching a component will be restricted based on the route it is within.
 *
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource(
 *     mercure=true,
 *     collectionOperations={
 *         "get",
 *         "post",
 *         "generate"={ "method"="POST", "path"="/routes/generate", "validation_groups"={ "Route:generate:write" } }
 *     },
 *     itemOperations={
 *         "get"={ "requirements"={"id"="(?!.+\/redirects$).+"}, "security"="is_granted('read_route', object)" },
 *         "delete"={ "requirements"={"id"="(.+)"}, "security"="is_granted('read_route', object)" },
 *         "put"={ "requirements"={"id"="(.+)"}, "security"="is_granted('read_route', object)" },
 *         "patch"={ "requirements"={"id"="(.+)"}, "security"="is_granted('read_route', object)" },
 *         "redirects"={
 *             "method"="GET",
 *             "path"="/routes/{id}/redirects",
 *             "requirements"={"id"="(.+)"},
 *             "security"="is_granted('read_route', object)",
 *             "normalization_context"={ "groups"={"Route:redirect:read"} },
 *             "defaults"={ "_api_item_operation_name"="route_redirects" }
 *         }
 *     }
 * )
 * @Assert\Expression(
 *     "!(this.getPage() == null & this.getPageData() == null & this.getRedirect() == null)",
 *     message="Please specify either page, pageData or redirect.",
 *     groups={"Route:generate:write", "Default"}
 * )
 * @Assert\Expression(
 *     "!(this.getPage() != null & this.getPageData() != null)",
 *     message="Please specify either page or pageData, not both.",
 *     groups={"Route:generate:write", "Default"}
 * )
 * @UniqueEntity("name", message="This route name is already in use.")
 * @UniqueEntity("path", message="This path is already in use.")
 */
class Route
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @Assert\NotBlank
     * @Groups({"Route:redirect:read"})
     */
    private string $path = '';

    /**
     * @Assert\NotNull
     */
    private string $name;

    private ?Route $redirect = null;

    /**
     * @Groups({"Route:redirect:read"})
     */
    private Collection $redirectedFrom;

    private ?Page $page = null;

    private ?AbstractPageData $pageData = null;

    public function __construct()
    {
        $this->redirectedFrom = new ArrayCollection();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getRedirect(): ?self
    {
        return $this->redirect;
    }

    public function setRedirect(?self $redirect): self
    {
        $this->redirect = $redirect;
        if ($redirect) {
            $redirect->addRedirectedFrom($this);
        }

        return $this;
    }

    /**
     * @return Collection|Route[]
     */
    public function getRedirectedFrom()
    {
        return $this->redirectedFrom;
    }

    /**
     * @param array|Collection $redirectedFrom
     */
    public function setRedirectedFrom($redirectedFrom): self
    {
        $isArray = \is_array($redirectedFrom);
        if (!$isArray && !$redirectedFrom instanceof Collection) {
            throw new \InvalidArgumentException('setRedirectedFrom requires an array or Collection');
        }
        $this->redirectedFrom = $isArray ? new ArrayCollection($redirectedFrom) : $redirectedFrom;

        return $this;
    }

    public function addRedirectedFrom(self $redirectedFrom): self
    {
        if (!$this->redirectedFrom->contains($redirectedFrom)) {
            $this->redirectedFrom->add($redirectedFrom);
        }

        return $this;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): self
    {
        $this->page = $page;
        if ($this->page) {
            $this->page->setRoute($this);
        }

        return $this;
    }

    public function getPageData(): ?AbstractPageData
    {
        return $this->pageData;
    }

    public function setPageData(?AbstractPageData $pageData): self
    {
        $this->pageData = $pageData;
        if ($this->pageData) {
            $this->pageData->setRoute($this);
        }

        return $this;
    }
}
