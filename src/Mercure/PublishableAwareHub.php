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

namespace Silverback\ApiComponentsBundle\Mercure;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\ItemNotFoundException;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

class PublishableAwareHub implements HubInterface
{
    public function __construct(private HubInterface $decorated, private PublishableStatusChecker $publishableStatusChecker, private IriConverterInterface $iriConverter)
    {
    }

    public function getUrl(): string
    {
        return $this->decorated->getUrl();
    }

    public function getPublicUrl(): string
    {
        return $this->decorated->getPublicUrl();
    }

    public function getProvider(): TokenProviderInterface
    {
        return $this->decorated->getProvider();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->decorated->getFactory();
    }

    public function publish(Update $update): string
    {
        if ($update->getData() && $data = json_decode($update->getData(), associative: true)) {
            try {
                $resource = $this->iriConverter->getResourceFromIri($data['@id']);
            } catch (ItemNotFoundException $e) {
                return $this->decorated->publish($update);
            }

            if ($this->publishableStatusChecker->getAnnotationReader()->isConfigured($resource) && !$this->publishableStatusChecker->isActivePublishedAt($resource)) {
                $update = new Update(topics: $update->getTopics(), data: $update->getData(), private: true, id: $update->getId(), type: $update->getType(), retry: $update->getRetry());
            }
        }

        return $this->decorated->publish($update);
    }
}
