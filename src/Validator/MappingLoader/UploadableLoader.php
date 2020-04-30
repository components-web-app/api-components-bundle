<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Validator\MappingLoader;

use Silverback\ApiComponentBundle\AnnotationReader\UploadableAnnotationReader;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

/**
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
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        if (!$this->uploadsHelper->isConfigured($metadata->getClassName())) {
            return false;
        }

        $fields = $this->uploadsHelper->getConfiguredProperties($metadata->getClassName(), true, false);

        foreach ($fields as $fileField) {
            $metadata->addPropertyConstraint($fileField, new Assert\NotNull());
        }

        return true;
    }
}
