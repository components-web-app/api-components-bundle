<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class HubStub implements HubInterface
{
    private static bool $unreachable = false;

    private LcobucciFactory $factory;

    public function __construct(LcobucciFactory $factory)
    {
        $this->factory = $factory;
    }

    public static function setUnreachable(bool $unreachable): void
    {
        self::$unreachable = $unreachable;
    }

    public function publish(Update $update): string
    {
        if (self::$unreachable) {
            throw new RuntimeException('Failed to send an update.', 0, new TransportException('Could not resolve host: example.com'));
        }

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

    public function getProtocolVersion(): ProtocolVersion
    {
        return ProtocolVersion::Legacy;
    }

    public function getCookieName(): string
    {
        return 'mercureAuthorization';
    }
}
