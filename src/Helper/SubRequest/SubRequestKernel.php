<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\SubRequest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class SubRequestKernel
{
    public function __construct(
        private readonly HttpKernelInterface $httpKernel,
        private readonly ?Stopwatch $stopwatch = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST, false);
        } catch (\Throwable $exception) {
            $this->closeStopwatchSection($request);

            throw $exception;
        }
    }

    private function closeStopwatchSection(Request $request): void
    {
        $sectionId = $request->attributes->get('_stopwatch_token');
        if (null === $this->stopwatch || !\is_string($sectionId)) {
            return;
        }

        try {
            $this->stopwatch->stopSection($sectionId);
        } catch (\LogicException) {
        }
    }
}
