<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Action;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class AbstractAction
{
    public static function getRequestFormat(Request $request): string
    {
        return $request->attributes->get('_format') ?: $request->getFormat($request->headers->get('CONTENT_TYPE')) ?: 'jsonld';
    }
}
