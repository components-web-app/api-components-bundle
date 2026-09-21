<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\MappingLoader;

use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Validator\Constraints\RequiresUploadedFile;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class UploadableLoader implements LoaderInterface
{
    public function __construct(
        private readonly UploadableAttributeReader $annotationReader,
        private readonly PublishableAttributeReader $publishableAttributeReader,
    ) {
    }

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $className = $metadata->getClassName();
        if (!$this->annotationReader->isConfigured($className)) {
            return false;
        }

        $groups = $this->getPublishedValidationGroups($className);

        $added = false;
        foreach ($this->annotationReader->getConfiguredProperties($className, true) as $fileProperty => $fieldConfiguration) {
            if (!$fieldConfiguration->requiredOnPublish) {
                continue;
            }

            $constraint = new RequiresUploadedFile(groups: $groups);
            $constraint->fileProperty = $fileProperty;
            $constraint->filenameProperty = $fieldConfiguration->property;
            if (null !== $fieldConfiguration->requiredOnPublishMessage) {
                $constraint->message = $fieldConfiguration->requiredOnPublishMessage;
            }

            $metadata->addConstraint($constraint);
            $added = true;
        }

        return $added;
    }

    /**
     * @return string[]
     */
    private function getPublishedValidationGroups(string $className): array
    {
        if ($this->publishableAttributeReader->isConfigured($className)) {
            $validationGroups = $this->publishableAttributeReader->getConfiguration($className)->validationGroups;
            if (!empty($validationGroups)) {
                return $validationGroups;
            }
        }

        return [\sprintf('%s:published', (new \ReflectionClass($className))->getShortName())];
    }
}
