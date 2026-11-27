<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Validator\Constraints\AdminPreferences;
use Silverback\ApiComponentsBundle\Validator\Constraints\AdminPreferencesValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class AdminPreferencesValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AdminPreferencesValidator();
    }

    /**
     * @return array{a: string}
     */
    private static function encodedAs(int $bytes): array
    {
        return ['a' => str_repeat('x', $bytes - \strlen('{"a":""}'))];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function validPreferences(): iterable
    {
        yield 'null' => [null];
        yield 'an empty object' => [[]];
        yield 'an object of the module' => [['topBar' => ['layouts', 'pages', 'ext:shop']]];
        yield 'an object of exactly 4096 bytes' => [self::encodedAs(4096)];
    }

    #[DataProvider('validPreferences')]
    public function test_an_object_or_null_up_to_4096_bytes_is_valid(mixed $value): void
    {
        $this->validator->validate($value, new AdminPreferences());

        $this->assertNoViolation();
    }

    public function test_the_boundary_objects_encode_to_their_stated_size(): void
    {
        self::assertSame(4096, \strlen(json_encode(self::encodedAs(4096), \JSON_THROW_ON_ERROR)));
        self::assertSame(4097, \strlen(json_encode(self::encodedAs(4097), \JSON_THROW_ON_ERROR)));
    }

    public function test_an_object_of_4097_bytes_is_too_large(): void
    {
        $this->validator->validate(self::encodedAs(4097), new AdminPreferences());

        $this->buildViolation('Admin preferences cannot be over {{ limit }} bytes of JSON.')
            ->setParameter('{{ limit }}', '4096')
            ->setCode(AdminPreferences::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    public function test_the_size_is_measured_on_the_json_encoding_not_the_characters(): void
    {
        $this->validator->validate(['a' => str_repeat('/', 2045)], new AdminPreferences());

        $this->buildViolation('Admin preferences cannot be over {{ limit }} bytes of JSON.')
            ->setParameter('{{ limit }}', '4096')
            ->setCode(AdminPreferences::TOO_LARGE_ERROR)
            ->assertRaised();
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function valuesThatAreNotAnObject(): iterable
    {
        yield 'a string' => ['layouts'];
        yield 'an integer' => [42];
        yield 'a float' => [4.2];
        yield 'a boolean' => [true];
        yield 'a list' => [['layouts', 'pages']];
        yield 'an oversized list' => [array_fill(0, 2000, 'xx')];
    }

    #[DataProvider('valuesThatAreNotAnObject')]
    public function test_a_value_that_is_not_an_object_is_a_violation(mixed $value): void
    {
        $this->validator->validate($value, new AdminPreferences());

        $this->buildViolation('Admin preferences must be an object or null.')
            ->setCode(AdminPreferences::NOT_AN_OBJECT_ERROR)
            ->assertRaised();
    }

    public function test_another_constraint_is_refused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(null, new NotBlank());
    }
}
