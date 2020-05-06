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

namespace Silverback\ApiComponentsBundle\Validator\MappingLoader;

use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class TimestampedLoader implements LoaderInterface
{
    private TimestampedAnnotationReader $annotationReader;

    public function __construct(TimestampedAnnotationReader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        if (!$this->annotationReader->isConfigured($metadata->getClassName())) {
            return false;
        }

        $configuration = $this->annotationReader->getConfiguration($metadata->getClassName());
        $shortName = (new \ReflectionClass($metadata->getClassName()))->getShortName();

        $metadata->addPropertyConstraint(
            $configuration->createdAtField,
            new Assert\NotNull(
                [
                    'groups' => [sprintf('%s:timestamped', $shortName)],
                    'message' => sprintf('%s should not be null', $configuration->createdAtField),
                ]
            )
        );

        $metadata->addPropertyConstraint(
            $configuration->modifiedAtField,
            new Assert\NotNull(
                [
                    'groups' => [sprintf('%s:timestamped', $shortName)],
                    'message' => sprintf('%s should not be null', $configuration->modifiedAtField),
                ]
            )
        );

        return true;
    }
}
