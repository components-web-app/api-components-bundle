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

namespace Silverback\ApiComponentsBundle\EventListener\Mercure;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Util\CorsTrait;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AddMercureTokenListener
{
    use CorsTrait;

    public function __construct(
        private readonly MercureAuthorization $mercureAuthorization
    ) {
    }

    /**
     * Sends the Mercure header on each response.
     * Probably lock this on the "/me" route.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        /** @var ?HttpOperation $operation */
        $operation = $request->attributes->get('_api_operation');
        // Prevent issues with NelmioCorsBundle
        if (!$operation || $this->isPreflightRequest($request) || $operation->getName() !== 'me') {
            return;
        }
        $cookie = $this->mercureAuthorization->createAuthorizationCookie();
        $event->getResponse()->headers->setCookie($cookie);
    }
}
