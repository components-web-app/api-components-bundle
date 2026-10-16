<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Plan;

/**
 * @internal
 */
enum MethodKind: string
{
    case Layout = 'Layout';
    case Page = 'Page';
    case PageData = 'PageData';
    case Redirects = 'Redirects';
}
