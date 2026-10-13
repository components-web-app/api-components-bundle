<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Entity;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedFileReportRecord;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedResourceReportRecord;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidatorClassMetadata;
use Symfony\Component\Validator\Validation;

class StringColumnLengthTest extends TestCase
{
    private const int DEFAULT_STRING_LENGTH = 255;

    private const array SERVER_WRITTEN_COLUMNS = [
        FileInfo::class => ['path', 'mimeType', 'storedFilter'],
        OrphanedResourceReportRecord::class => ['generatedAt'],
        OrphanedFileReportRecord::class => ['generatedAt'],
        AbstractUser::class => ['password', 'newPasswordConfirmationToken', 'newEmailConfirmationToken', 'emailAddressVerifyToken'],
    ];

    #[DataProvider('provideWritableStringColumns')]
    public function test_a_writable_string_column_has_a_length_constraint_of_its_column_length(string $class, string $field, int $columnLength): void
    {
        $propertyConstraints = self::propertyConstraints($class, $field);
        $lengthConstraints = array_values(array_filter($propertyConstraints, static fn (Constraint $constraint): bool => $constraint instanceof Length));

        self::assertNotEmpty($lengthConstraints, \sprintf('%s::$%s is a VARCHAR(%d) with no Length constraint, so an over-long value fails at the database instead of validation.', $class, $field, $columnLength));

        $requiredGroups = ['Default'];
        foreach ($propertyConstraints as $constraint) {
            if (!$constraint instanceof Length) {
                $requiredGroups = [...$requiredGroups, ...$constraint->groups];
            }
        }

        foreach (array_unique($requiredGroups) as $group) {
            $covering = array_filter($lengthConstraints, static fn (Length $length): bool => \in_array($group, $length->groups, true) && $columnLength === $length->max);
            self::assertNotEmpty($covering, \sprintf('%s::$%s has no Length constraint with a max of %d in the "%s" validation group.', $class, $field, $columnLength, $group));
        }
    }

    public function test_every_server_written_column_is_a_string_column(): void
    {
        $stringColumns = [];
        foreach (self::stringColumns() as [$class, $field]) {
            $stringColumns[] = $class . '::' . $field;
        }

        foreach (self::SERVER_WRITTEN_COLUMNS as $class => $fields) {
            foreach ($fields as $field) {
                self::assertContains($class . '::' . $field, $stringColumns);
            }
        }
    }

    public static function provideWritableStringColumns(): iterable
    {
        foreach (self::stringColumns() as [$class, $field, $length]) {
            foreach (self::SERVER_WRITTEN_COLUMNS as $serverWrittenClass => $fields) {
                if (is_a($class, $serverWrittenClass, true) && \in_array($field, $fields, true)) {
                    continue 2;
                }
            }

            yield $class . '::' . $field => [$class, $field, $length];
        }
    }

    /**
     * @return iterable<array{class-string, string, int}>
     */
    private static function stringColumns(): iterable
    {
        $driver = new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity']);
        $reflectionService = new RuntimeReflectionService();
        foreach ($driver->getAllClassNames() as $class) {
            $metadata = new ClassMetadata($class);
            $metadata->initializeReflection($reflectionService);
            $driver->loadMetadataForClass($class, $metadata);
            foreach ($metadata->fieldMappings as $field => $mapping) {
                if ('string' !== $mapping->type) {
                    continue;
                }

                yield [$class, $field, $mapping->length ?? self::DEFAULT_STRING_LENGTH];
            }
        }
    }

    /**
     * @return Constraint[]
     */
    private static function propertyConstraints(string $class, string $field): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->addMethodMapping('loadValidatorMetadata')
            ->getValidator();
        $metadata = $validator->getMetadataFor($class);
        self::assertInstanceOf(ValidatorClassMetadata::class, $metadata);

        $constraints = [];
        foreach ($metadata->getPropertyMetadata($field) as $propertyMetadata) {
            $constraints = [...$constraints, ...$propertyMetadata->getConstraints()];
        }

        return $constraints;
    }
}
