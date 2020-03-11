<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity
 */
class Collection extends AbstractComponent
{
    /**
     * @ORM\Column(nullable=false)
     * @Assert\NotNull(message="The resource class for a collection component is required")
     */
    private string $resourceClass;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $perPage;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    private ?array $defaultQueryParameters;

    public function getResourceClass(): string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(string $resourceClass): self
    {
        $this->resourceClass = $resourceClass;

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
}
