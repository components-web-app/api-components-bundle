<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;

/**
 * A mercure-enabled resource with ROLE_ADMIN security, used to test secure_subscriptions.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource(mercure: true, security: "is_granted('ROLE_ADMIN')")]
#[ORM\Entity]
class DummySecuredMercureResource
{
    use IdTrait;
}
