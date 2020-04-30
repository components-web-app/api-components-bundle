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

use Silverback\ApiComponentBundle\Annotation\Uploads;
use Silverback\ApiComponentBundle\Helper\UploadsHelper;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadsLoader implements LoaderInterface
{
    private UploadsHelper $uploadsHelper;

    public function __construct(UploadsHelper $uploadsHelper)
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

        /** @var Uploads $configuration */
        $configuration = $this->uploadsHelper->getConfiguration($metadata->getClassName());
        $metadata->addPropertyConstraint($configuration->fieldName, new Assert\NotNull());

        return true;
    }
}
