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
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
use Silverback\ApiComponentsBundle\Event\FormSuccessEvent;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class EntityPersistFormListener implements EntityPersistFormListenerInterface
{
    use ClassMetadataTrait;

    private TimestampedAnnotationReader $timestampedAnnotationReader;
    private TimestampedDataPersister $timestampedDataPersister;
    private string $formType;
    private string $dataClass;

    public function __construct(string $formType, string $dataClass)
    {
        $this->formType = $formType;
        $this->dataClass = $dataClass;
    }

    public function init(
        ManagerRegistry $registry,
        TimestampedAnnotationReader $timestampedAnnotationReader,
        TimestampedDataPersister $timestampedDataPersister
    ): void {
        $this->initRegistry($registry);
        $this->timestampedAnnotationReader = $timestampedAnnotationReader;
        $this->timestampedDataPersister = $timestampedDataPersister;
    }

    public function __invoke(FormSuccessEvent $event)
    {
        if (
            $this->formType !== $event->getForm()->formType ||
            !is_a($data = $event->getFormData(), $this->dataClass, true)
        ) {
            return;
        }
        $entityManager = $this->registry->getManagerForClass($this->dataClass);
        if (!$entityManager) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for %s', $this->dataClass));
        }

        if ($this->timestampedAnnotationReader->isConfigured($data)) {
            $this->timestampedDataPersister->persistTimestampedFields($data, true);
        }

        $entityManager->persist($data);
        $entityManager->flush();
        $event->result = $data;
    }
}
