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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Security\Voter\SiteConfigParameterVoter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource(
    mercure: true,
    paginationEnabled: false,
)]
#[GetCollection(paginationItemsPerPage: 100)]
#[Post]
#[Delete(security: SiteConfigParameter::API_SECURITY)]
#[Put(security: SiteConfigParameter::API_SECURITY)]
#[Patch(security: SiteConfigParameter::API_SECURITY)]
#[Get(security: SiteConfigParameter::API_SECURITY)]
class SiteConfigParameter
{
    private const string API_SECURITY = "is_granted('" . SiteConfigParameterVoter::NAME . "', object)";

    #[ORM\Id]
    #[ORM\Column(unique: true, nullable: false)]
    #[ApiProperty(identifier: true)]
    private ?string $key = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[ApiProperty]
    private array|string|null $value = null;

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getValue(): array|string|null
    {
        return $this->value;
    }

    public function setValue(array|string|null $value): static
    {
        $this->value = $value;

        return $this;
    }
}
