<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Action\Health;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class LivenessAction
{
    public function __invoke(): JsonResponse
    {
        return HealthResponse::neverStored(['status' => 'ok'], Response::HTTP_OK);
    }
}
