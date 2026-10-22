<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedInterface;
use Silverback\ApiComponentsBundle\Exception\ResourceVersionConflictException;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
final readonly class ResourceVersionConflictStateProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $decorated
     */
    public function __construct(
        private ProcessorInterface $decorated,
        private ManagerRegistry $registry,
        private PublishableAttributeReader $publishableAttributeReader,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $insertsDraft = $this->insertsDraftOfPublished($data);
        $entityManager = $data instanceof VersionedInterface ? $this->registry->getManagerForClass($data::class) : null;
        try {
            if (!$entityManager instanceof EntityManagerInterface) {
                return $this->decorated->process($data, $operation, $uriVariables, $context);
            }

            return $entityManager->getConnection()->transactional(fn () => $this->decorated->process($data, $operation, $uriVariables, $context));
        } catch (OptimisticLockException $exception) {
            throw new ResourceVersionConflictException($exception);
        } catch (UniqueConstraintViolationException $exception) {
            if (!$insertsDraft) {
                throw $exception;
            }
            throw new ResourceVersionConflictException($exception);
        }
    }

    private function insertsDraftOfPublished(mixed $data): bool
    {
        if (!\is_object($data) || !$this->publishableAttributeReader->isConfigured($data)) {
            return false;
        }
        $entityManager = $this->registry->getManagerForClass($data::class);
        if (!$entityManager instanceof EntityManagerInterface || !$entityManager->getUnitOfWork()->isScheduledForInsert($data)) {
            return false;
        }
        $associationName = $this->publishableAttributeReader->getConfiguration($data)->associationName;

        return null !== $entityManager->getClassMetadata($data::class)->getFieldValue($data, $associationName);
    }
}
