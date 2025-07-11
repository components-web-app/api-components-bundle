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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource(operations: [
    new GetCollection(order: ['createdAt' => 'DESC'], security: "is_granted('ROLE_SUPER_ADMIN')"),
    new Post(security: "is_granted('ROLE_SUPER_ADMIN')"),
    new Get(security: "is_granted('ROLE_SUPER_ADMIN') or object.getId() == user.getId()"),
    new Put(security: "is_granted('ROLE_SUPER_ADMIN') or object.getId() == user.getId()"),
    new Patch(security: "is_granted('ROLE_SUPER_ADMIN') or object.getId() == user.getId()"),
    new Delete(security: "is_granted('ROLE_SUPER_ADMIN')"),
])]
#[ORM\Entity]
#[ORM\Table(name: '`user`')]
class User extends AbstractUser
{
    //    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    //    {
    //        $metadata->addPropertyConstraint('username', new Assert\Email([
    //            'message' => 'Please enter a valid email address.',
    //        ]));
    //    }

    public function __construct(string $username = '', string $emailAddress = '', bool $emailAddressVerified = false, array $roles = ['ROLE_USER'], string $password = '', bool $enabled = true)
    {
        parent::__construct($username, $emailAddress, $emailAddressVerified, $roles, $password, $enabled);
    }
}
