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

use ApiPlatform\GraphQl\Subscription\MercureSubscriptionIriGeneratorInterface as GraphQlMercureSubscriptionIriGeneratorInterface;
use ApiPlatform\GraphQl\Subscription\SubscriptionManagerInterface as GraphQlSubscriptionManagerInterface;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\State\SerializerContextBuilderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\HttpCache\ResourceChangedPropagatorInterface;
use Silverback\ApiComponentsBundle\Message\PublishMercureUpdatesMessage;
use Silverback\ApiComponentsBundle\Utility\PublicationDate;
use Silverback\ApiComponentsBundle\Utility\ResourceClassInfoTrait;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;

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
    private const string ADMIN_TOPIC_QUERY = '?draft=1';
    private const string SERIALIZATION_ID_PATTERN = '~"@id":"[^"]*(?:\\.well-known(?:\\\\/|/)genid|media_objects)(?:\\\\/|/)[^"]*"~';

    private readonly ?ExpressionLanguage $expressionLanguage;
    private \SplObjectStorage $createdObjects;
    private \SplObjectStorage $updatedObjects;
    private \SplObjectStorage $deletedObjects;
    private bool $isPropagating = false;

    public function __construct(
        private readonly HubRegistry $hubRegistry,
        private readonly IriConverterInterface $iriConverter,
        private readonly SerializerContextBuilderInterface $serializerContextBuilder,
        private readonly RequestStack $requestStack,
        private readonly array $formats,
        private readonly AnonymousResourceReader $anonymousResourceReader,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly ManagerRegistry $registry,
        ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        ResourceClassResolverInterface $resourceClassResolver,
        ?MessageBusInterface $messageBus = null,
        private readonly ?GraphQlSubscriptionManagerInterface $graphQlSubscriptionManager = null,
        private readonly ?GraphQlMercureSubscriptionIriGeneratorInterface $graphQlMercureSubscriptionIriGenerator = null,
        ?ExpressionLanguage $expressionLanguage = null,
        private readonly ?CwaCollectorData $collectorData = null,
        private readonly ?LoggerInterface $logger = null,
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
                new ExpressionFunction('iri', static fn (string $apiResource, int $referenceType = UrlGeneratorInterface::ABS_URL): string => \sprintf('iri(%s, %d)', $apiResource, $referenceType), static fn (array $arguments, $apiResource, int $referenceType = UrlGeneratorInterface::ABS_URL): string => $iriConverter->getIriFromResource($apiResource, $referenceType))
            );
        }
    }

    public function reset(): void
    {
        $this->createdObjects = new \SplObjectStorage();
        $this->updatedObjects = new \SplObjectStorage();
        $this->deletedObjects = new \SplObjectStorage();
        $this->isPropagating = false;
    }

    public function add(object $item, ?string $type = null): void
    {
        $property = \sprintf('%sObjects', $type);
        if (!isset($this->{$property})) {
            throw new \InvalidArgumentException(\sprintf('Cannot collect Mercure resource with type %s : the property %s does not exist.', $type, $property));
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
        $objectData = ['id' => $id, 'iri' => $iri, 'mercureOptions' => $this->normalizeMercureOptions($options, $object), 'leftPublic' => false];

        if ('deletedObjects' === $property) {
            $this->createdObjects->offsetUnset($object);
            $this->updatedObjects->offsetUnset($object);
            $this->deletedObjects[$object] = $objectData;

            return;
        }

        $objectData['leftPublic'] = ($this->{$property}[$object]['leftPublic'] ?? false) || $this->leftPublicInThisWrite($object);
        $this->{$property}[$object] = $objectData;
    }

    private function isDraft(object $object): bool
    {
        $attributeReader = $this->publishableStatusChecker->getAttributeReader();
        if (!$attributeReader->isConfigured($object)) {
            return false;
        }

        if (!$this->publishableStatusChecker->isActivePublishedAt($object)) {
            return true;
        }

        $manager = $this->registry->getManagerForClass($this->getObjectClass($object));

        return $manager instanceof EntityManagerInterface && null !== $manager->getClassMetadata($this->getObjectClass($object))->getFieldValue($object, $attributeReader->getConfiguration($object)->associationName);
    }

    private function leftPublicInThisWrite(object $object): bool
    {
        $manager = $this->registry->getManagerForClass($this->getObjectClass($object));
        if (!$manager instanceof EntityManagerInterface) {
            return false;
        }

        $changeSet = $manager->getUnitOfWork()->getEntityChangeSet($object);
        $attributeReader = $this->publishableStatusChecker->getAttributeReader();
        if ($attributeReader->isConfigured($object) && $this->leftActiveDate($changeSet, $attributeReader->getConfiguration($object)->fieldName)) {
            return true;
        }

        if ($object instanceof Route) {
            return $this->leftActiveDate($changeSet, 'liveAt');
        }

        if ($object instanceof AbstractPage) {
            return (isset($changeSet['route']) && null !== $changeSet['route'][0] && null === $changeSet['route'][1])
                || isset($changeSet['parentPage'])
                || isset($changeSet['parentPageData']);
        }

        return false;
    }

    private function leftActiveDate(array $changeSet, string $fieldName): bool
    {
        return isset($changeSet[$fieldName]) && PublicationDate::isActive($changeSet[$fieldName][0]) && !PublicationDate::isActive($changeSet[$fieldName][1]);
    }

    private function getObjectMercureOptions(object $object): ?array
    {
        if (null === $resourceClass = $this->getResourceClass($object)) {
            return null;
        }

        try {
            $options = $this->resourceMetadataFactory->create($resourceClass)->getOperation()->getMercure() ?? false;
        } catch (OperationNotFoundException) {
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
            throw new InvalidArgumentException(\sprintf('The value of the "mercure" attribute of the "%s" resource class must be a boolean, an array of options or an expression returning this array, "%s" given.', $resourceClass, \gettype($options)));
        }

        foreach ($options as $key => $value) {
            if (!isset(self::ALLOWED_KEYS[$key])) {
                throw new InvalidArgumentException(\sprintf('The option "%s" set in the "mercure" attribute of the "%s" resource does not exist. Existing options: "%s"', $key, $resourceClass, implode('", "', array_keys(self::ALLOWED_KEYS))));
            }
        }

        return $options;
    }

    private function normalizeMercureOptions(array $options, object $object): array
    {
        $options['enable_async_update'] ??= true;

        if ($options['topics'] ?? false) {
            $topics = [];
            foreach ((array) $options['topics'] as $topic) {
                if (!\is_string($topic) || !str_starts_with($topic, '@=')) {
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
        if ($this->isPropagating) {
            return;
        }
        $this->isPropagating = true;
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
            $this->isPropagating = false;
            $this->reset();
        }
    }

    private function getObjectData(object $object, string $iri, array $options): string
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

        return $this->serializer->serialize($object, key($this->formats), $context);
    }

    private function publishUpdate(object $object, array $objectData, string $type): void
    {
        $options = $objectData['mercureOptions'];

        $updates = array_merge(
            $this->buildResourceUpdates($object, $objectData, $type),
            $this->getGraphQlSubscriptionUpdates($object, $options, $type)
        );

        foreach ($updates as $update) {
            foreach ($update->getTopics() as $topic) {
                $this->collectorData?->recordMercurePublication($topic);
            }
        }

        if ($options['enable_async_update'] && $this->messageBus) {
            try {
                $this->dispatch(1 === \count($updates) ? $updates[0] : new PublishMercureUpdatesMessage($updates, $options['hub'] ?? null));
            } catch (MercureRuntimeException|HttpClientExceptionInterface $exception) {
                $this->logPublishFailure($updates, $objectData['iri'], $exception);
            }

            return;
        }

        foreach ($updates as $update) {
            try {
                $this->hubRegistry->getHub($options['hub'] ?? null)->publish($update);
            } catch (MercureRuntimeException|HttpClientExceptionInterface $exception) {
                $this->logPublishFailure([$update], $objectData['iri'], $exception);
            }
        }
    }

    /**
     * @return list<Update>
     */
    private function buildResourceUpdates(object $object, array $objectData, string $type): array
    {
        $options = $objectData['mercureOptions'];
        $topics = $options['topics'] ?? $objectData['iri'];
        $deleteData = json_encode(['@id' => $objectData['id']], \JSON_THROW_ON_ERROR);

        if ('delete' === $type) {
            return [$this->buildUpdate($topics, $deleteData, $options)];
        }

        if (isset($options['data'])) {
            return [$this->buildUpdate($topics, $options['data'], $options)];
        }

        if ($this->isDraft($object)) {
            $writerData = $this->getWriterData($object, $objectData['iri'], $options);
            $updates = [];
            if ($objectData['leftPublic']) {
                $updates[] = $this->buildUpdate($this->withoutAdminQuery($topics), json_encode(['@id' => $this->withoutAdminQuery($objectData['id'])], \JSON_THROW_ON_ERROR), $options);
            }
            if (null !== $writerData) {
                $updates[] = $this->buildUpdate($this->withAdminQuery($topics), $writerData, $options, true);
            }

            return $updates;
        }

        $publicData = $this->anonymousResourceReader->read($objectData['id'], $this->getAcceptedMimeType());
        $updates = [];
        if (null !== $publicData) {
            $updates[] = $this->buildUpdate($topics, $publicData, $options);
        } elseif ($objectData['leftPublic']) {
            $updates[] = $this->buildUpdate($topics, $deleteData, $options);
        }
        $writerData = $this->getWriterData($object, $objectData['iri'], $options);
        if (null !== $writerData && (null === $publicData || $this->withoutSerializationIds($writerData) !== $this->withoutSerializationIds($publicData))) {
            $updates[] = $this->buildUpdate($this->withAdminQuery($topics), $writerData, $options, true);
        }

        return $updates;
    }

    private function withoutSerializationIds(string $data): string
    {
        return (string) preg_replace(self::SERIALIZATION_ID_PATTERN, '', $data);
    }

    private function getWriterData(object $object, string $iri, array $options): ?string
    {
        try {
            return $this->getObjectData($object, $iri, $options);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function getAcceptedMimeType(): string
    {
        $mimeTypes = (array) current($this->formats);

        return (string) reset($mimeTypes);
    }

    /**
     * @param string|string[] $topics
     *
     * @return string|string[]
     */
    private function withAdminQuery(string|array $topics): string|array
    {
        if (\is_array($topics)) {
            return array_map($this->withAdminQuery(...), $topics);
        }

        return str_ends_with($topics, self::ADMIN_TOPIC_QUERY) ? $topics : $topics . self::ADMIN_TOPIC_QUERY;
    }

    /**
     * @param string|string[] $topics
     *
     * @return string|string[]
     */
    private function withoutAdminQuery(string|array $topics): string|array
    {
        if (\is_array($topics)) {
            return array_map($this->withoutAdminQuery(...), $topics);
        }

        return str_ends_with($topics, self::ADMIN_TOPIC_QUERY) ? substr($topics, 0, -\strlen(self::ADMIN_TOPIC_QUERY)) : $topics;
    }

    /**
     * @param list<Update> $updates
     */
    private function logPublishFailure(array $updates, string $resourceIri, \Throwable $exception): void
    {
        $topics = array_merge(...array_map(static fn (Update $update): array => $update->getTopics(), $updates));
        $this->logger?->error(\sprintf('The Mercure update for "%s" could not be published and was dropped; the write itself was saved: %s', implode('", "', $topics), $exception->getMessage()), [
            'topics' => $topics,
            'resource' => $resourceIri,
            'exception' => $exception,
        ]);
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
    private function buildUpdate(string|array $iri, string $data, array $options, bool $private = false): Update
    {
        return new Update($iri, $data, $private || ($options['private'] ?? false), $options['id'] ?? null, $options['type'] ?? null, $options['retry'] ?? null);
    }
}
