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

namespace Silverback\ApiComponentsBundle\Security\Voter;

use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractRoutableVoter extends Voter
{
    public const READ_ROUTABLE = 'read_routable';

    protected function supports(string $attribute, $subject): bool
    {
        if (self::READ_ROUTABLE !== $attribute) {
            return false;
        }
        if (!$subject instanceof RoutableInterface) {
            throw new \InvalidArgumentException(\sprintf('$subject must be of type `%s`', RoutableInterface::class));
        }

        return true;
    }
}
