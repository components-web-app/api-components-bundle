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

namespace Silverback\ApiComponentsBundle\EventListener\Form;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class EntityPersistFormListener
{
    use ClassMetadataTrait;

    private ?TimestampedAttributeReader $timestampedAnnotationReader;
    private ?TimestampedDataPersister $timestampedDataPersister;
    private ?UserEventListener $userEventListener;
    /** @var NormalizerInterface|DenormalizerInterface|null */
    private ?NormalizerInterface $normalizer;
    private ?UserDataProcessor $userDataProcessor;
    private string $formType;
    private string $dataClass;
    private bool $returnFormDataOnSuccess;

    public function __construct(string $formType, string $dataClass, bool $returnFormDataOnSuccess = true)
    {
        $this->formType = $formType;
        $this->dataClass = $dataClass;
        $this->returnFormDataOnSuccess = $returnFormDataOnSuccess;
    }

    public function init(
        ManagerRegistry $registry,
        TimestampedAttributeReader $timestampedAnnotationReader,
        TimestampedDataPersister $timestampedDataPersister,
        UserEventListener $userEventListener,
        NormalizerInterface $normalizer,
        UserDataProcessor $userDataProcessor
    ): void {
        if (!$normalizer instanceof DenormalizerInterface) {
            throw new InvalidArgumentException(sprintf('$normalizer must also implement %s', DenormalizerInterface::class));
        }
        $this->initRegistry($registry);
        $this->timestampedAnnotationReader = $timestampedAnnotationReader;
        $this->timestampedDataPersister = $timestampedDataPersister;
        $this->userEventListener = $userEventListener;
        $this->normalizer = $normalizer;
        $this->userDataProcessor = $userDataProcessor;
    }

    public function __invoke(FormSuccessEvent $event)
    {
        // This is not a sub-request because forms have greater permissions to create entitites with whatever properties wanted.

        if (
            $this->formType !== $event->getForm()->formType ||
            !is_a($data = $event->getFormData(), $this->dataClass, true)
        ) {
            return;
        }

        if ($this->timestampedAnnotationReader->isConfigured($data)) {
            $this->timestampedDataPersister->persistTimestampedFields($data, true);
        }

        $entityManager = $this->registry->getManagerForClass($this->dataClass);
        if (!$entityManager) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for %s', $this->dataClass));
        }

        if ($data instanceof AbstractUser) {
            $uow = $entityManager->getUnitOfWork();
            $oldData = $uow->getOriginalEntityData($data);
            $oldUser = null;
            if (\count($oldData)) {
                $normalized = $this->normalizer->normalize($oldData);
                /** @var AbstractUser $oldUser */
                $oldUser = $this->normalizer->denormalize($normalized, \get_class($data));
            }

            $this->userDataProcessor->processChanges($data, $oldUser);
            $this->userEventListener->postWrite($data, $oldUser);
        }

        $entityManager->persist($data);
        $entityManager->flush();

        if ($this->returnFormDataOnSuccess) {
            $event->result = $data;
        }
    }
}
