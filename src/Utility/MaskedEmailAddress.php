<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Utility;

final class MaskedEmailAddress
{
    public static function of(string $emailAddress): string
    {
        $at = strrpos($emailAddress, '@');
        if (false === $at) {
            return mb_substr($emailAddress, 0, 1) . '***';
        }

        return mb_substr($emailAddress, 0, 1) . '***' . substr($emailAddress, $at);
    }
}
