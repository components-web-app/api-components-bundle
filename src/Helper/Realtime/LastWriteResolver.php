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

use Silverback\ApiComponentsBundle\Mercure\AnonymousResourceReader;
use Silverback\ApiComponentsBundle\Realtime\RealtimeEventRequest;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class LastWriteResolver
{
    public const string HEADER = 'X-CWA-Client-Id';

    public function __construct(
        private RequestStack $requestStack,
        private RealtimeUserResolver $userResolver,
    ) {
    }

    /**
     * @return array{clientId: string, userId: string}|null
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

        return ['clientId' => $clientId, 'userId' => $user['id']];
    }
}
