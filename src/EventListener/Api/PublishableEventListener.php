<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Silverback\ApiComponentsBundle\Validator\PublishableValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableEventListener
{
    use ApiEventListenerTrait;
    use ClassMetadataTrait;

    public const VALID_TO_PUBLISH_HEADER = 'valid-to-publish';
    public const VALID_PUBLISHED_QUERY = 'validate_published';

    private PublishableAttributeReader $publishableAttributeReader;

    public function __construct(
        private readonly PublishableStatusChecker $publishableStatusChecker,
        ManagerRegistry $registry,
        private readonly ValidatorInterface $validator,
    ) {
        $this->publishableAttributeReader = $publishableStatusChecker->getAttributeReader();
        $this->initRegistry($registry);
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        $attributes = $this->getAttributes($request);

        /**
         * @var PublishableTrait|null $data
         */
        $data = $attributes['data'];

        if (
            null === $data
            || !$this->publishableAttributeReader->isConfigured($attributes['class'])
            || $attributes['operation'] instanceof CollectionOperationInterface
        ) {
            return;
        }
        $response = $event->getResponse();

        $configuration = $this->publishableAttributeReader->getConfiguration($attributes['class']);
        $classMetadata = $this->getClassMetadata($attributes['class']);
        $draftResource = $classMetadata->getFieldValue($data, $configuration->reverseAssociationName) ?? $data;

        /** @var \DateTime|null $publishedAt */
        $publishedAt = $classMetadata->getFieldValue($draftResource, $configuration->fieldName);
        if ($publishedAt && $publishedAt > new \DateTime()) {
            $response->setExpires($publishedAt);
        }

        if (!$this->publishableStatusChecker->isGranted($attributes['class'])) {
            return;
        }

        if ($response->isClientError()) {
            $response->headers->set(self::VALID_TO_PUBLISH_HEADER, '0');

            return;
        }

        try {
            $this->validator->validate($data, [PublishableValidator::PUBLISHED_KEY => true]);
            $response->headers->set(self::VALID_TO_PUBLISH_HEADER, '1');
        } catch (ValidationException $exception) {
            $response->headers->set(self::VALID_TO_PUBLISH_HEADER, '0');
            if (
                true === $request->query->getBoolean(self::VALID_PUBLISHED_QUERY, false)
                && \in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH], true)
            ) {
                throw $exception;
            }
        }
    }
}
