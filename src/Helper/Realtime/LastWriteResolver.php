<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Realtime;

use Psr\Clock\ClockInterface;
use Silverback\ApiComponentsBundle\Mercure\AnonymousResourceReader;
use Silverback\ApiComponentsBundle\Realtime\RealtimeEventRequest;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class LastWriteResolver
{
    public const string HEADER = 'X-CWA-Client-Id';
    public const string AT_ATTRIBUTE = '_cwa_last_write_at';

    public function __construct(
        private RequestStack $requestStack,
        private RealtimeUserResolver $userResolver,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{clientId: string, userId: string, at: string}|null
     */
    public function resolve(): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || $request->isMethodSafe() || $request->attributes->has(AnonymousResourceReader::REQUEST_ATTRIBUTE)) {
            return null;
        }

        $clientId = (string) $request->headers->get(self::HEADER);
        $user = $this->userResolver->resolve();
        if (null === $user || !RealtimeEventRequest::isClientId($clientId)) {
            return null;
        }

        $at = $request->attributes->get(self::AT_ATTRIBUTE);
        if (!\is_string($at)) {
            $at = $this->clock->now()->format(\DateTimeInterface::RFC3339_EXTENDED);
            $request->attributes->set(self::AT_ATTRIBUTE, $at);
        }

        return ['clientId' => $clientId, 'userId' => $user['id'], 'at' => $at];
    }
}
