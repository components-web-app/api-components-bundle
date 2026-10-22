<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Exception;

use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

final class ResourceVersionConflictException extends PreconditionFailedHttpException implements ExceptionInterface
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('The resource has changed since the version given in If-Match.', $previous);
    }
}
