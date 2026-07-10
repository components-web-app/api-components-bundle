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

use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Validator\Constraints\RequiresUploadedFile;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

/**
 * Adds a RequiresUploadedFile class constraint (in the `{ShortName}:published` group) for every
 * `#[UploadableField(requiredOnPublish: true)]`, so publishing requires a file per flagged field.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class UploadableLoader implements LoaderInterface
{
    public function __construct(private readonly UploadableAttributeReader $annotationReader)
    {
    }

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $className = $metadata->getClassName();
        if (!$this->annotationReader->isConfigured($className)) {
            return false;
        }

        $shortName = (new \ReflectionClass($className))->getShortName();

        $added = false;
        foreach ($this->annotationReader->getConfiguredProperties($className, true) as $fileProperty => $fieldConfiguration) {
            if (!$fieldConfiguration->requiredOnPublish) {
                continue;
            }

            $constraint = new RequiresUploadedFile(groups: [\sprintf('%s:published', $shortName)]);
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
}
