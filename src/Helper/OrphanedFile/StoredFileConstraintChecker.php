<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedFile;

use Silverback\ApiComponentsBundle\Validator\Constraints\UploadedImage;
use Silverback\ApiComponentsBundle\Validator\StoredFile\StoredFileConstraintValidatorFactory;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class StoredFileConstraintChecker
{
    private readonly ValidatorInterface $storedFileValidator;

    public function __construct(
        private readonly MetadataFactoryInterface $metadataFactory,
        ?ConstraintValidatorFactoryInterface $compositeValidatorFactory = null,
        int|float|null $defaultMaxPixels = UploadedImage::DEFAULT_MAX_PIXELS,
    ) {
        $this->storedFileValidator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new StoredFileConstraintValidatorFactory($compositeValidatorFactory ?? new ConstraintValidatorFactory(), $defaultMaxPixels))
            ->getValidator();
    }

    /**
     * @param class-string $class
     * @param list<string> $groups
     *
     * @return list<Constraint>
     */
    public function constraints(string $class, string $property, array $groups = [Constraint::DEFAULT_GROUP]): array
    {
        $metadata = $this->metadataFactory->getMetadataFor($class);
        if (!$metadata instanceof ClassMetadataInterface || !$metadata->hasPropertyMetadata($property)) {
            return [];
        }
        $constraints = [];
        foreach ($groups as $group) {
            foreach ($metadata->getPropertyMetadata($property) as $propertyMetadata) {
                foreach ($propertyMetadata->findConstraints($group) as $constraint) {
                    if (!\in_array($constraint, $constraints, true)) {
                        $constraints[] = $constraint;
                    }
                }
            }
        }

        return $constraints;
    }

    /**
     * @param list<Constraint> $constraints
     * @param list<string>     $groups
     *
     * @return list<string>
     */
    public function violations(StoredFile $file, array $constraints, array $groups = [Constraint::DEFAULT_GROUP]): array
    {
        $messages = [];
        foreach ($constraints as $constraint) {
            $group = array_values(array_intersect($groups, $constraint->groups))[0] ?? null;
            if (null === $group) {
                continue;
            }
            foreach ($this->storedFileValidator->validate($file, $constraint, [$group]) as $violation) {
                $messages[] = (string) $violation->getMessage();
            }
        }

        return $messages;
    }
}
