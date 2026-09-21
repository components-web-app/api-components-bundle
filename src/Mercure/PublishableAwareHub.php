<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Mercure;

use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\RemoteHubInterface;
use Symfony\Component\Mercure\Update;

class PublishableAwareHub implements RemoteHubInterface
{
    public function __construct(private HubInterface $decorated, private PublishableStatusChecker $publishableStatusChecker, private IriConverterInterface $iriConverter, private readonly ?CwaCollectorData $collectorData = null)
    {
    }

    public function getUrl(): string
    {
        if (!method_exists($this->decorated, 'getUrl')) {
            throw new \LogicException(\sprintf('The decorated hub "%s" has no internal URL.', $this->decorated::class));
        }

        return $this->decorated->getUrl();
    }

    public function getPublicUrl(): string
    {
        return $this->decorated->getPublicUrl();
    }

    public function getProvider(): TokenProviderInterface
    {
        if (!method_exists($this->decorated, 'getProvider')) {
            throw new \LogicException(\sprintf('The decorated hub "%s" has no token provider.', $this->decorated::class));
        }

        return $this->decorated->getProvider();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->decorated->getFactory();
    }

    public function getProtocolVersion(): ProtocolVersion
    {
        return $this->decorated->getProtocolVersion();
    }

    public function getCookieName(): string
    {
        return $this->decorated->getCookieName();
    }

    public function publish(Update $update): string
    {
        if ($update->getData() && $data = json_decode($update->getData(), true, 512, \JSON_THROW_ON_ERROR)) {
            try {
                $resource = $this->iriConverter->getResourceFromIri($data['@id']);
            } catch (ItemNotFoundException $e) {
                return $this->decorated->publish($update);
            }

            if ($this->publishableStatusChecker->getAttributeReader()->isConfigured($resource) && !$this->publishableStatusChecker->isActivePublishedAt($resource)) {
                $update = new Update(topics: $update->getTopics(), data: $update->getData(), private: true, id: $update->getId(), type: $update->getType(), retry: $update->getRetry());
                $this->collectorData?->recordMercurePrivateUpgrade($update->getTopics(), $resource::class);
            }
        }

        return $this->decorated->publish($update);
    }
}
