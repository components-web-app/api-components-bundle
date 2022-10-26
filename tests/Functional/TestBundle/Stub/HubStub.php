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

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class HubStub implements HubInterface
{
    private LcobucciFactory $factory;

    public function __construct(LcobucciFactory $factory)
    {
        $this->factory = $factory;
    }

    public function publish(Update $update): string
    {
        $postData = [
            'topic' => $update->getTopics(),
            'data' => $update->getData(),
            'private' => $update->isPrivate() ? 'on' : null,
            'id' => $update->getId(),
            'type' => $update->getType(),
            'retry' => $update->getRetry(),
        ];

        return json_encode($postData);
    }

    public function getUrl(): string
    {
        return 'https://example.com/.well-known/mercure';
    }

    public function getPublicUrl(): string
    {
        return 'https://example.com/.well-known/mercure';
    }

    public function getProvider(): TokenProviderInterface
    {
        return new StaticTokenProvider('foo');
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->factory;
    }
}
