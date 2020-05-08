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

namespace Silverback\ApiComponentsBundle\Serializer;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 */
interface SerializeFormatResolverInterface
{
    public function getFormat(): string;

    public function getFormatFromRequest(Request $request): string;
}
