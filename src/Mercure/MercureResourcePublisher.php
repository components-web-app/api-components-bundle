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

use ApiPlatform\Exception\InvalidArgumentException as LegacyInvalidArgumentException;
use ApiPlatform\Exception\OperationNotFoundException as LegacyOperationNotFoundException;
use ApiPlatform\GraphQl\Subscription\MercureSubscriptionIriGeneratorInterface as GraphQlMercureSubscriptionIriGeneratorInterface;
use ApiPlatform\GraphQl\Subscription\SubscriptionManagerInterface as GraphQlSubscriptionManagerInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\Serializer\SerializerContextBuilderInterface;
use Doctrine\ORM\PersistentCollection;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;
use Silverback\ApiComponentsBundle\Utility\ResourceClassInfoTrait;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;
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
        private readonly SerializerContextBuilderInterface $serializerContextBuilder,
        private readonly RequestStack $requestStack,
        private readonly array $formats,
        ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        ResourceClassResolverInterface $resourceClassResolver,
        MessageBusInterface $messageBus = null,
        private readonly ?GraphQlSubscriptionManagerInterface $graphQlSubscriptionManager = null,
        private readonly ?GraphQlMercureSubscriptionIriGeneratorInterface $graphQlMercureSubscriptionIriGenerator = null,
        ExpressionLanguage $expressionLanguage = null
    ) {
        $this->reset();
        $this->resourceClassResolver = $resourceClassResolver;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->messageBus = $messageBus;
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

    public function add(object $item, string $type = null): void
    {
        $property = sprintf('%sObjects', $type);
        if (!isset($this->{$property})) {
            throw new \InvalidArgumentException(sprintf('Cannot collect Mercure resource with type %s : the property %s does not exist.', $type, $property));
        }

        if (!is_iterable($item)) {
            $this->storeObjectToPublish($item, $property);

            return;
        }

        if ($item instanceof PersistentCollection) {
            $item = clone $item;
        }

        foreach ($item as $i) {
            $this->storeObjectToPublish($i, $property);
        }
    }

    private function storeObjectToPublish(object $object, string $property): void
    {
        $options = $this->getObjectMercureOptions($object);
        if (null === $options) {
            return;
        }

        $id = $this->iriConverter->getIriFromResource($object);
        $iri = $this->iriConverter->getIriFromResource($object, UrlGeneratorInterface::ABS_URL);
        $objectData = ['id' => $id, 'iri' => $iri, 'mercureOptions' => $this->normalizeMercureOptions($options)];

        if ('deletedObjects' === $property) {
            $this->createdObjects->detach($object);
            $this->updatedObjects->detach($object);
            $this->deletedObjects[$object] = $objectData;

            return;
        }

        $this->{$property}[$object] = $objectData;
    }

    private function getObjectMercureOptions(object $object): ?array
    {
        if (null === $resourceClass = $this->getResourceClass($object)) {
            return null;
        }

        try {
            $options = $this->resourceMetadataFactory->create($resourceClass)->getOperation()->getMercure() ?? false;
        } catch (OperationNotFoundException|LegacyOperationNotFoundException) {
            return null;
        }

        if (\is_string($options)) {
            if (null === $this->expressionLanguage) {
                throw new RuntimeException('The Expression Language component is not installed. Try running "composer require symfony/expression-language".');
            }

            $options = $this->expressionLanguage->evaluate($options, ['object' => $object]);
        }

        if (false === $options) {
            return null;
        }

        if (true === $options) {
            return [];
        }

        if (!\is_array($options)) {
            throw new InvalidArgumentException(sprintf('The value of the "mercure" attribute of the "%s" resource class must be a boolean, an array of options or an expression returning this array, "%s" given.', $resourceClass, \gettype($options)));
        }

        foreach ($options as $key => $value) {
            if (!isset(self::ALLOWED_KEYS[$key])) {
                throw new InvalidArgumentException(sprintf('The option "%s" set in the "mercure" attribute of the "%s" resource does not exist. Existing options: "%s"', $key, $resourceClass, implode('", "', self::ALLOWED_KEYS)));
            }
        }

        return $options;
    }

    private function normalizeMercureOptions(array $options): array
    {
        $options['enable_async_update'] ??= true;

        if ($options['topics'] ?? false) {
            $topics = [];
            foreach ((array) $options['topics'] as $topic) {
                if (!\is_string($topic) || !str_starts_with($topic, '@=')) {
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

        return $options;
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

    private function getObjectData(object $object, string $iri)
    {
        $resourceClass = $this->getObjectClass($object);

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            $request = Request::create($iri);
        }
        $attributes = [
            'operation' => $this->resourceMetadataFactory->create($resourceClass)->getOperation(),
            'resource_class' => $resourceClass,
        ];
        $baseContext = $this->serializerContextBuilder->createFromRequest($request, true, $attributes);
        $context = array_merge($baseContext, $options['normalization_context'] ?? []);

        return $options['data'] ?? $this->serializer->serialize($object, key($this->formats), $context);
    }

    private function publishUpdate(object $object, array $objectData, string $type): void
    {
        $options = $objectData['mercureOptions'];
        $iri = $options['topics'] ?? $objectData['iri'];

        $getDeletedObjectData = static function () use ($objectData) {
            return json_encode(['@id' => $objectData['id']], \JSON_THROW_ON_ERROR);
        };

        if ('delete' === $type) {
            $data = $getDeletedObjectData();
        } else {
            try {
                $data = $this->getObjectData($object, $iri);
            } catch (InvalidArgumentException|LegacyInvalidArgumentException) {
                // the object may have been deleted at the database level with delete cascades...
                $type = 'delete';
                $data = $getDeletedObjectData();
            }
        }

        $updates = array_merge([$this->buildUpdate($iri, $data, $options)], $this->getGraphQlSubscriptionUpdates($object, $options, $type));

        foreach ($updates as $update) {
            if ($options['enable_async_update'] && $this->messageBus) {
                $this->dispatch($update);
                continue;
            }

            $this->hubRegistry->getHub($options['hub'] ?? null)->publish($update);
        }
    }

    /**
     * @return Update[]
     */
    private function getGraphQlSubscriptionUpdates(object $object, array $options, string $type): array
    {
        if ('update' !== $type || !$this->graphQlSubscriptionManager || !$this->graphQlMercureSubscriptionIriGenerator) {
            return [];
        }

        $payloads = $this->graphQlSubscriptionManager->getPushPayloads($object);

        $updates = [];
        foreach ($payloads as [$subscriptionId, $data]) {
            $updates[] = $this->buildUpdate(
                $this->graphQlMercureSubscriptionIriGenerator->generateTopicIri($subscriptionId),
                (string) (new JsonResponse($data))->getContent(),
                $options
            );
        }

        return $updates;
    }

    /**
     * @param string|string[] $iri
     */
    private function buildUpdate(string|array $iri, string $data, array $options): Update
    {
        return new Update($iri, $data, $options['private'] ?? false, $options['id'] ?? null, $options['type'] ?? null, $options['retry'] ?? null);
    }
}
