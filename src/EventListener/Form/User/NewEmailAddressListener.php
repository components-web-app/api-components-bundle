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

namespace Silverback\ApiComponentsBundle\EventListener\Form\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class NewEmailAddressListener
{
    public function __construct()
    {
        parent::__construct(NewEmailAddressType::class, AbstractUser::class);
    }
}
