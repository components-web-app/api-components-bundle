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

namespace Silverback\ApiComponentsBundle\Validator;

use ApiPlatform\Validator\ValidatorInterface;
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;

/**
 * Builds and add validation group for timestamped resources.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class TimestampedValidator implements ValidatorInterface
{
    private ValidatorInterface $decorated;
    private TimestampedAnnotationReader $annotationReader;

    public function __construct(ValidatorInterface $decorated, TimestampedAnnotationReader $annotationReader)
    {
        $this->decorated = $decorated;
        $this->annotationReader = $annotationReader;
    }

    /**
     * {@inheritdoc}
     */
    public function validate($data, array $context = []): void
    {
        if (
            \is_object($data) &&
            $this->annotationReader->isConfigured($data)
        ) {
            $context['groups'] = $context['groups'] ?? ['Default'];
            $context['groups'][] = (new \ReflectionClass(\get_class($data)))->getShortName() . ':timestamped';
        }

        $this->decorated->validate($data, $context);
    }
}
