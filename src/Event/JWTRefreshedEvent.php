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

namespace Silverback\ApiComponentsBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class JWTRefreshedEvent extends Event
{
    public const EVENT_NAME = 'lexik_jwt_authentication.on_jwt_refreshed';

    private string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
