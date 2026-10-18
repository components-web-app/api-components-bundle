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
    public const string STOPWATCH_EVENT = 'cwa.sub_request';

    public function __construct(
        private readonly HttpKernelInterface $httpKernel,
        private readonly ?Stopwatch $stopwatch = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->stopwatch?->start(self::STOPWATCH_EVENT, 'section');

        try {
            return $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST, false);
        } finally {
            $this->restoreCallerSection($request);
        }
    }

    private function restoreCallerSection(Request $request): void
    {
        if (null === $this->stopwatch) {
            return;
        }

        $sectionId = $request->attributes->get('_stopwatch_token');
        if (!$this->stopwatch->isStarted(self::STOPWATCH_EVENT) && \is_string($sectionId)) {
            try {
                $this->stopwatch->stopSection($sectionId);
            } catch (\LogicException) {
            }
        }

        if ($this->stopwatch->isStarted(self::STOPWATCH_EVENT)) {
            $this->stopwatch->stop(self::STOPWATCH_EVENT);
        }
    }
}
