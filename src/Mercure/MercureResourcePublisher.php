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
use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Symfony\Messenger\DispatchTrait;
use ApiPlatform\Util\ResourceClassInfoTrait;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class MercureResourcePublisher implements SerializerAwareInterface, ResourceChangedPropagatorInterface
{
    use DispatchTrait;
    use ResourceClassInfoTrait;
    use SerializerAwareTrait;
    private const ALLOWED_KEYS = [
        'topics' => true,
        'data' => true,
        'private' => true,
        'id' => true,
        'type' => true,
        'retry' => true,
        'normalization_context' => true,
        'hub' => true,
        'enable_async_update' => true,
    ];

    private readonly ?ExpressionLanguage $expressionLanguage;
    private \SplObjectStorage $createdObjects;
    private \SplObjectStorage $updatedObjects;
    private \SplObjectStorage $deletedObjects;

    // Do we want MessageBusInterface instead ? we don't have messenger installed yet, probably just use the default hub for now
    public function __construct(
        private readonly HubRegistry $hubRegistry,
        private readonly IriConverterInterface $iriConverter,
        ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        ResourceClassResolverInterface $resourceClassResolver,
        private readonly array $formats,
        ?ExpressionLanguage $expressionLanguage = null
    ) {
        $this->reset();
        $this->resourceClassResolver = $resourceClassResolver;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->expressionLanguage = $expressionLanguage ?? (class_exists(ExpressionLanguage::class) ? new ExpressionLanguage() : null);
        if ($this->expressionLanguage) {
            $rawurlencode = ExpressionFunction::fromPhp('rawurlencode', 'escape');
            $this->expressionLanguage->addFunction($rawurlencode);

            $this->expressionLanguage->addFunction(
                new ExpressionFunction('iri', static fn (string $apiResource, int $referenceType = UrlGeneratorInterface::ABS_URL): string => sprintf('iri(%s, %d)', $apiResource, $referenceType), static fn (array $arguments, $apiResource, int $referenceType = UrlGeneratorInterface::ABS_URL): string => $iriConverter->getIriFromResource($apiResource, $referenceType))
            );
        }
    }

    public function reset(): void
    {
        $this->createdObjects = new \SplObjectStorage();
        $this->updatedObjects = new \SplObjectStorage();
        $this->deletedObjects = new \SplObjectStorage();
    }

    public function collectResource($entity, ?string $type = null): void
    {
        // this is not needed for Mercure.
        // this clears cache for endpoints getting collections etc.
        // Mercure will only update for individual items
    }

    public function collectItems($items, ?string $type = null): void
    {
        $property = sprintf('%sObjects', $type);
        if (!isset($this->{$property})) {
            throw new \InvalidArgumentException(sprintf('Cannot collect Mercure resource with type %s : the property %s does not exist.', $type, $property));
        }

        foreach ($items as $item) {
            $this->storeObjectToPublish($item, $property);
        }
    }

    /**
     * @throws \ApiPlatform\Exception\ResourceClassNotFoundException
     *
     * @description See: ApiPlatform\Doctrine\EventListener\PublishMercureUpdatesListener
     */
    private function storeObjectToPublish(object $object, string $property): void
    {
        if (null === $resourceClass = $this->getResourceClass($object)) {
            return;
        }

        try {
            $options = $this->resourceMetadataFactory->create($resourceClass)->getOperation()->getMercure() ?? false;
        } catch (OperationNotFoundException) {
            return;
        }

        if (\is_string($options)) {
            if (null === $this->expressionLanguage) {
                throw new RuntimeException('The Expression Language component is not installed. Try running "composer require symfony/expression-language".');
            }

            $options = $this->expressionLanguage->evaluate($options, ['object' => $object]);
        }

        if (false === $options) {
            return;
        }

        if (true === $options) {
            $options = [];
        }

        if (!\is_array($options)) {
            throw new InvalidArgumentException(sprintf('The value of the "mercure" attribute of the "%s" resource class must be a boolean, an array of options or an expression returning this array, "%s" given.', $resourceClass, \gettype($options)));
        }

        foreach ($options as $key => $value) {
            if (!isset(self::ALLOWED_KEYS[$key])) {
                throw new InvalidArgumentException(sprintf('The option "%s" set in the "mercure" attribute of the "%s" resource does not exist. Existing options: "%s"', $key, $resourceClass, implode('", "', self::ALLOWED_KEYS)));
            }
        }

        $options['enable_async_update'] ??= true;

        if ($options['topics'] ?? false) {
            $topics = [];
            foreach ((array) $options['topics'] as $topic) {
                if (!\is_string($topic)) {
                    $topics[] = $topic;
                    continue;
                }

                if (!str_starts_with($topic, '@=')) {
                    $topics[] = $topic;
                    continue;
                }

                if (null === $this->expressionLanguage) {
                    throw new \LogicException('The "@=" expression syntax cannot be used without the Expression Language component. Try running "composer require symfony/expression-language".');
                }

                $topics[] = $this->expressionLanguage->evaluate(substr($topic, 2), ['object' => $object]);
            }

            $options['topics'] = $topics;
        }

        $id = $this->iriConverter->getIriFromResource($object);
        $iri = $this->iriConverter->getIriFromResource($object, UrlGeneratorInterface::ABS_URL);
        $objectData = ['id' => $id, 'iri' => $iri, 'mercureOptions' => $options];

        if ('deletedObjects' === $property) {
            $this->createdObjects->detach($object);
            $this->updatedObjects->detach($object);
            $deletedObject = (object) [
                'id' => $this->iriConverter->getIriFromResource($object),
                'iri' => $this->iriConverter->getIriFromResource($object, UrlGeneratorInterface::ABS_URL),
            ];
            $this->deletedObjects[$deletedObject] = $objectData;
            return;
        }

        $this->{$property}[$object] = $objectData;
    }

    public function propagate(): void
    {
        try {
            foreach ($this->createdObjects as $object) {
                $this->publishUpdate($object, $this->createdObjects[$object], 'create');
            }

            foreach ($this->updatedObjects as $object) {
                $this->publishUpdate($object, $this->updatedObjects[$object], 'update');
            }

            foreach ($this->deletedObjects as $object) {
                $this->publishUpdate($object, $this->deletedObjects[$object], 'delete');
            }
        } finally {
            $this->reset();
        }
    }

    private static function getDeletedIriAndData(array $objectData): array
    {
        // By convention, if the object has been deleted, we send only its IRI.
        // This may change in the feature, because it's not JSON Merge Patch compliant,
        // and I'm not a fond of this approach.
        $iri = $options['topics'] ?? $objectData['iri'];
        /** @var string $data */
        $data = json_encode(['@id' => $objectData['id']], \JSON_THROW_ON_ERROR);
        return [$iri, $data];
    }

    private function publishUpdate(object $object, array $objectData, string $type): void
    {
        $options = $objectData['mercureOptions'];

        if ($object instanceof \stdClass) {
            [$iri, $data] = self::getDeletedIriAndData($objectData);
        } else {
            $resourceClass = $this->getObjectClass($object);
            $context = $options['normalization_context'] ?? $this->resourceMetadataFactory->create($resourceClass)->getOperation()->getNormalizationContext() ?? [];
            try {
                $iri = $options['topics'] ?? $this->iriConverter->getIriFromResource($object, UrlGeneratorInterface::ABS_URL);
                $data = $options['data'] ?? $this->serializer->serialize($object, key($this->formats), $context);
            } catch (InvalidArgumentException) {
                // the object may have been deleted at the database level with delete cascades...
                [$iri, $data] = self::getDeletedIriAndData($objectData);
                $type = 'delete';
            }
        }

        $updates = [$this->buildUpdate($iri, $data, $options)];

        foreach ($updates as $update) {
//            if ($options['enable_async_update'] && $this->messageBus) {
//                $this->dispatch($update);
//                continue;
//            }

            $this->hubRegistry->getHub($options['hub'] ?? null)->publish($update);
        }
    }

    /**
     * @param string|string[] $iri
     */
    private function buildUpdate(string|array $iri, string $data, array $options): Update
    {
        return new Update($iri, $data, $options['private'] ?? false, $options['id'] ?? null, $options['type'] ?? null, $options['retry'] ?? null);
    }
}
