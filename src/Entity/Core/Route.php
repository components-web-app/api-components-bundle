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

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\RouteStateProvider;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Although a user will be able to get the routes and the tree of data down to getting the ID for a component
 * fetching a component will be restricted based on the route it is within.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[Assert\Expression(
    '!(this.getPage() == null & this.getPageData() == null & this.getRedirect() == null)',
    message: 'Please specify either page, pageData or redirect.',
    groups: ['Route:generate:write', 'Default']
)]
#[Assert\Expression(
    '!(this.getPage() != null & this.getPageData() != null)',
    message: 'Please specify either page or pageData, not both.',
    groups: ['Route:generate:write', 'Default']
)]
#[UniqueEntity('name', 'This route name is already in use.')]
#[UniqueEntity('path', 'This path is already in use.')]
#[ApiResource(
    mercure: true,
    paginationClientItemsPerPage: true,
    provider: RouteStateProvider::class
)]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'path'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(OrSearchFilter::class, properties: ['path' => 'ipartial'])]
#[Post]
#[GetCollection(order: ['createdAt' => 'DESC'])]
#[Delete(requirements: Route::API_REQUIREMENTS, security: Route::API_SECURITY)]
#[Put(requirements: Route::API_REQUIREMENTS, security: Route::API_SECURITY)]
#[Patch(requirements: Route::API_REQUIREMENTS, security: Route::API_SECURITY)]
#[Get(requirements: ['id' => "(?!.+\/redirects$).+"], security: Route::API_SECURITY)]
// Custom endpoints
#[Post(uriTemplate: '/routes/generate{._format}', validationContext: ['groups' => ['Route:generate:write']])]
#[Get(uriTemplate: '/routes/{id}/redirects{._format}', defaults: ['_api_item_operation_name' => 'route_redirects'], requirements: Route::API_REQUIREMENTS, order: ['createdAt' => 'DESC'], normalizationContext: ['groups' => ['Route:redirect:read']], security: Route::API_SECURITY)]
#[Get(uriTemplate: '/routes_manifest/{id}{._format}', defaults: ['_api_item_operation_name' => 'route_resources'], requirements: Route::API_REQUIREMENTS, normalizationContext: ['groups' => ['Route:manifest:read']], security: Route::API_SECURITY)]
#[Silverback\Timestamped]
class Route
{
    use IdTrait;
    use TimestampedTrait;

    private const array API_REQUIREMENTS = ['id' => '(.+)'];
    private const string API_SECURITY = "is_granted('read_route', object)";

    #[Assert\NotBlank]
    #[Groups(['Route:redirect:read'])]
    private string $path = '';

    #[Assert\NotNull]
    #[Groups(['Route:redirect:read'])]
    private string $name;

    #[Groups(['Route:redirect:read'])]
    private ?Route $redirect = null;

    #[Groups(['Route:redirect:read'])]
    private Collection $redirectedFrom;

    #[Groups(['Route:manifest:read', 'Route:redirect:read'])]
    private ?Page $page = null;

    #[Groups(['Route:manifest:read', 'Route:redirect:read'])]
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

    #[Groups(['Route:cwa_resource:read:ROLE_ADMIN'])]
    public function getAssociatedResources()
    {
        return [
            'redirect' => $this->redirect?->path,
            'page' => $this->page?->reference,
            'pageData' => $this->pageData?->getTitle(),
            'pageDataType' => $this->pageData ? \get_class($this->pageData) : null,
        ];
    }
}
