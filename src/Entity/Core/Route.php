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
 *         "generate"={ "method"="POST", "path"="/routes/generate" }
 *     },
 *     itemOperations={
 *         "get"={ "requirements"={"id"="(.+)"}, "security"="is_granted(object)" }
 *     }
 * )
 * @Assert\Expression(
 *     "!(this.getPage() == null & this.getPageData() == null)",
 *     message="Please specify either page or pageData."
 * )
 * @Assert\Expression(
 *     "!(this.getPage() != null & this.getPageData() != null)",
 *     message="Please specify either page or pageData, not both."
 * )
 * @UniqueEntity("name", message="The route name must be unique.")
 * @UniqueEntity("path", message="The route path must be unique.")
 */
class Route
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @Assert\NotNull()
     */
    private string $path;

    /**
     * @Assert\NotNull()
     */
    private string $name;

    private ?Route $redirect = null;

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

        return $this;
    }

    public function getRedirectedFrom()
    {
        return $this->redirectedFrom;
    }

    public function setRedirectedFrom($redirectedFrom): self
    {
        $this->redirectedFrom = $redirectedFrom;

        return $this;
    }

    public function getPage(): ?Page
    {
        return $this->page ?? ($this->pageData ? $this->pageData->page : null);
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
