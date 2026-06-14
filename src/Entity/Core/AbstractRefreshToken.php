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

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\RefreshToken\RefreshToken;
use Silverback\ApiComponentsBundle\Repository\Core\RefreshTokenRepository;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
#[ORM\MappedSuperclass(repositoryClass: RefreshTokenRepository::class)]
abstract class AbstractRefreshToken extends RefreshToken
{
    use IdTrait;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    protected ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'expired_at', type: 'datetime_immutable')]
    protected ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    protected ?int $version = null;
}
