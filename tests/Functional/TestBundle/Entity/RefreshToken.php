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

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractRefreshToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
#[ORM\Entity]
class RefreshToken extends AbstractRefreshToken
{
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    protected ?UserInterface $user = null;
}
