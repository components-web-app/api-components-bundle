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

namespace Silverback\ApiComponentBundle\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Silverback\ApiComponentBundle\Helper\UploadableHelper;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableListener
{
    private UploadableHelper $uploadableHelper;

    public function __construct(UploadableHelper $uploadableHelper)
    {
        $this->uploadableHelper = $uploadableHelper;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        /** @var ClassMetadataInfo $metadata */
        $metadata = $eventArgs->getClassMetadata();
        if (!$this->uploadableHelper->isConfigured($metadata->getName())) {
            return;
        }

        $em = $eventArgs->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }

        foreach ($metadata->getReflectionProperties() as $property) {
            if (!$this->uploadableHelper->isFieldConfigured($property)) {
                continue;
            }

            $fieldConfiguration = $this->uploadableHelper->getFieldConfiguration($property);
            if (!$metadata->hasField($fieldConfiguration->property)) {
                $metadata->mapField([
                    'fieldName' => $fieldConfiguration->property,
                    'type' => 'string',
                    'nullable' => true,
                ]);
            }
        }
        // todo Add security if no UploadableField has been configured on related entity
    }
}
