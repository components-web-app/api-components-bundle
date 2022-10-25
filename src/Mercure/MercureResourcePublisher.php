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
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Symfony\Messenger\DispatchTrait;
use ApiPlatform\Util\ResourceClassInfoTrait;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class MercureResourcePublisher implements SerializerAwareInterface, ResourceChangedPropagatorInterface
{
    use DispatchTrait;
    use ResourceClassInfoTrait;
    use SerializerAwareTrait;

    // Do we want MessageBusInterface instead ? we don't have messenger installed yet, probably just use the default hub for now
    public function __construct(
        private readonly HubInterface $hub,
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory
    ) {
    }

    public function collectResource($entity): void
    {
        // this is not needed for Mercure.
        // this clears cache for endpoints getting collections etc.
        // Mercure will only update for individual items
    }

    public function collectItems($items): void
    {
        // TODO: Implement collectItems() method.
    }

    public function propagate(): void
    {
        // TODO: Implement propagate() method.
    }

//    public function publishResourceUpdate(object $object): void
//    {
//        $resourceClass = $this->getObjectClass($object);
//        $operation = $this->resourceMetadataCollectionFactory->create($resourceClass)->getOperation();
//        $mercureOptions = $operation->getMercure() ?? [];
//        $context = $operation->getNormalizationContext() ?? [];
//
//        $iri = $this->iriConverter->getIriFromResource($object, UrlGeneratorInterface::ABS_URL);
//        $data = $this->serializer->serialize($object, 'jsonld', $context);
//
//        $this->hub->publish(new Update(data: $data, topics: [$iri], private: $mercureOptions['private'] ?? false));
//    }
}
