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
    ) {
        $this->storedFileValidator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new StoredFileConstraintValidatorFactory($compositeValidatorFactory ?? new ConstraintValidatorFactory()))
            ->getValidator();
    }

    /**
     * @param class-string $class
     *
     * @return list<Constraint>
     */
    public function constraints(string $class, string $property): array
    {
        $metadata = $this->metadataFactory->getMetadataFor($class);
        if (!$metadata instanceof ClassMetadataInterface || !$metadata->hasPropertyMetadata($property)) {
            return [];
        }
        $constraints = [];
        foreach ($metadata->getPropertyMetadata($property) as $propertyMetadata) {
            array_push($constraints, ...$propertyMetadata->findConstraints(Constraint::DEFAULT_GROUP));
        }

        return $constraints;
    }

    /**
     * @param list<Constraint> $constraints
     *
     * @return list<string>
     */
    public function violations(StoredFile $file, array $constraints): array
    {
        $messages = [];
        foreach ($this->storedFileValidator->validate($file, $constraints, [Constraint::DEFAULT_GROUP]) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
