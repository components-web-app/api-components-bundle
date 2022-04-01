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

namespace Silverback\ApiComponentsBundle\Entity\Component;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Validator\Constraints as AcbAssert;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ORM\Entity]
class Collection extends AbstractComponent
{
    #[ORM\Column(nullable: false)]
    #[Assert\NotNull(message: 'The resource iri for a collection component is required')]
    #[AcbAssert\ResourceIri]
    private ?string $resourceIri;

    #[ORM\Column(nullable: true, type: 'integer')]
    private ?int $perPage = null;

    #[ORM\Column(nullable: true, type: 'json')]
    private ?array $defaultQueryParameters = null;

    #[ApiProperty(writable: false)]
    private ?array $collection = null;

    public function getResourceIri(): ?string
    {
        return $this->resourceIri;
    }

    public function setResourceIri(?string $resourceIri): self
    {
        $this->resourceIri = $resourceIri;

        return $this;
    }

    public function getPerPage(): ?int
    {
        return $this->perPage;
    }

    public function setPerPage(?int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function getDefaultQueryParameters(): ?array
    {
        return $this->defaultQueryParameters;
    }

    public function setDefaultQueryParameters(?array $defaultQueryParameters): self
    {
        $this->defaultQueryParameters = $defaultQueryParameters;

        return $this;
    }

    public function getCollection(): ?array
    {
        return $this->collection;
    }

    public function setCollection(?array $collection): self
    {
        $this->collection = $collection;

        return $this;
    }
}
