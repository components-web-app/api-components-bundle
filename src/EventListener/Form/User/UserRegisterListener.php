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

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\EventListener\Form\EntityPersistFormListener;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedHelper;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserRegisterListener extends EntityPersistFormListener
{
    public function __construct(ManagerRegistry $registry, TimestampedAnnotationReader $timestampedAnnotationReader, TimestampedHelper $timestampedHelper)
    {
        parent::__construct($registry, $timestampedAnnotationReader, $timestampedHelper, UserRegisterType::class, AbstractUser::class);
    }
}
