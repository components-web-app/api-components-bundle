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

use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

/**
 * NOT CURRENTLY IN USE - NO LONGER NEED FOR UPLOADABLE BUT A GOOD REFERENCE FOR TIMESTAMPED WORK.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableLoader implements LoaderInterface
{
    private UploadableAnnotationReader $uploadsHelper;

    public function __construct(UploadableAnnotationReader $uploadsHelper)
    {
        $this->uploadsHelper = $uploadsHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        if (!$this->uploadsHelper->isConfigured($metadata->getClassName())) {
            return false;
        }

        $fields = $this->uploadsHelper->getConfiguredProperties($metadata->getClassName(), true, false);

        foreach ($fields as $fileField) {
            $metadata->addPropertyConstraint($fileField, new Assert\NotNull(['groups' => sprintf('%s:create', $metadata->getClassName())]));
        }

        return true;
    }
}
