<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Entity\Core;

use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @internal
 */
#[Silverback\Timestamped]
#[UniqueEntity('route', message: 'The route must be unique.')]
#[Assert\Expression(
    'this.getParentPage() === null || this.getParentPageData() === null',
    message: 'A page can only have one parent: either a Page or a PageData, not both.'
)]
abstract class AbstractPage implements RoutableInterface
{
    use IdTrait;
    use TimestampedTrait;

    #[Groups(['Route:manifest:read'])]
    protected ?Route $route = null;

    #[Groups(['Route:manifest:read'])]
    protected ?Page $parentPage = null;

    #[Groups(['Route:manifest:read'])]
    protected ?AbstractPageData $parentPageData = null;

    protected ?string $title = 'Unnamed Page';

    protected ?string $metaDescription = null;

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(?Route $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getParentPage(): ?Page
    {
        return $this->parentPage;
    }

    public function setParentPage(?Page $parentPage): self
    {
        $this->parentPage = $parentPage;

        return $this;
    }

    public function getParentPageData(): ?AbstractPageData
    {
        return $this->parentPageData;
    }

    public function setParentPageData(?AbstractPageData $parentPageData): self
    {
        $this->parentPageData = $parentPageData;

        return $this;
    }

    public function getParentPageRoute(): ?Route
    {
        return $this->parentPage?->getRoute() ?? $this->parentPageData?->getRoute();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }
}
