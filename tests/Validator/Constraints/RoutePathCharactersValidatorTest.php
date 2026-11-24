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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Validator\Constraints\RoutePathCharacters;
use Silverback\ApiComponentsBundle\Validator\Constraints\RoutePathCharactersValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class RoutePathCharactersValidatorTest extends ConstraintValidatorTestCase
{
    private ?ObjectManager $manager = null;

    /** @var array<string, mixed> */
    private array $originalData = [];

    protected function createValidator(): ConstraintValidatorInterface
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturnCallback(fn (): array => $this->originalData);
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $this->manager = $manager;
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn (string $class): ?ObjectManager => Route::class === $class ? $this->manager : null);

        return new RoutePathCharactersValidator($registry);
    }

    public static function forbiddenPaths(): iterable
    {
        yield 'a space' => ['/about us'];
        yield 'a tab' => ["/about\tus"];
        yield 'a newline' => ["/about\nus"];
        yield 'a carriage return' => ["/about\rus"];
        yield 'a no-break space' => ["/about\u{00A0}us"];
        yield 'an em space' => ["/about\u{2003}us"];
        yield 'a line separator' => ["/about\u{2028}us"];
        yield 'an ideographic space' => ["/about\u{3000}us"];
        yield 'a trailing space' => ['/about '];
        yield 'a question mark' => ['/about?us'];
        yield 'a hash' => ['/about#us'];
    }

    public static function allowedPaths(): iterable
    {
        yield 'a plain path' => ['/about-us'];
        yield 'several segments' => ['/conference/2027/day-one'];
        yield 'the root' => ['/'];
        yield 'punctuation a URL path may hold' => ['/a_b.c~d!e$f&g\'h(i)j*k+l,m;n=o:p@q%20r'];
        yield 'letters outside ASCII' => ['/über/日本'];
        yield 'an empty path' => [''];
    }

    #[DataProvider('forbiddenPaths')]
    public function test_a_path_with_a_forbidden_character_is_a_violation(string $path): void
    {
        $this->validator->validate($path, new RoutePathCharacters());

        $this->buildViolation('A path cannot contain whitespace, "?" or "#".')
            ->setInvalidValue($path)
            ->setCode(RoutePathCharacters::FORBIDDEN_CHARACTER_ERROR)
            ->assertRaised();
    }

    #[DataProvider('allowedPaths')]
    public function test_a_path_with_no_forbidden_character_passes(string $path): void
    {
        $this->validator->validate($path, new RoutePathCharacters());

        $this->assertNoViolation();
    }

    public function test_the_constraints_message_is_used(): void
    {
        $constraint = new RoutePathCharacters();
        $constraint->message = 'No spaces.';

        $this->validator->validate('/about us', $constraint);

        $this->buildViolation('No spaces.')
            ->setInvalidValue('/about us')
            ->setCode(RoutePathCharacters::FORBIDDEN_CHARACTER_ERROR)
            ->assertRaised();
    }

    public function test_a_stored_route_whose_forbidden_path_is_unchanged_passes(): void
    {
        $this->setObject((new Route())->setPath('/about us'));
        $this->originalData = ['path' => '/about us'];

        $this->validator->validate('/about us', new RoutePathCharacters());

        $this->assertNoViolation();
    }

    public function test_a_stored_route_whose_path_changed_to_a_forbidden_path_is_a_violation(): void
    {
        $this->setObject((new Route())->setPath('/about us'));
        $this->originalData = ['path' => '/about'];

        $this->validator->validate('/about us', new RoutePathCharacters());

        $this->buildViolation('A path cannot contain whitespace, "?" or "#".')
            ->setInvalidValue('/about us')
            ->setCode(RoutePathCharacters::FORBIDDEN_CHARACTER_ERROR)
            ->assertRaised();
    }

    public function test_a_new_route_with_a_forbidden_path_is_a_violation(): void
    {
        $this->setObject((new Route())->setPath('/about us'));

        $this->validator->validate('/about us', new RoutePathCharacters());

        $this->buildViolation('A path cannot contain whitespace, "?" or "#".')
            ->setInvalidValue('/about us')
            ->setCode(RoutePathCharacters::FORBIDDEN_CHARACTER_ERROR)
            ->assertRaised();
    }

    public function test_an_object_that_is_not_a_route_is_never_taken_as_stored(): void
    {
        $this->setObject(new \stdClass());
        $this->originalData = ['path' => '/about us'];

        $this->validator->validate('/about us', new RoutePathCharacters());

        $this->buildViolation('A path cannot contain whitespace, "?" or "#".')
            ->setInvalidValue('/about us')
            ->setCode(RoutePathCharacters::FORBIDDEN_CHARACTER_ERROR)
            ->assertRaised();
    }

    public function test_a_route_with_no_entity_manager_is_never_taken_as_stored(): void
    {
        $this->manager = null;
        $this->setObject((new Route())->setPath('/about us'));
        $this->originalData = ['path' => '/about us'];

        $this->validator->validate('/about us', new RoutePathCharacters());

        $this->buildViolation('A path cannot contain whitespace, "?" or "#".')
            ->setInvalidValue('/about us')
            ->setCode(RoutePathCharacters::FORBIDDEN_CHARACTER_ERROR)
            ->assertRaised();
    }

    public function test_a_validator_with_no_registry_checks_every_path(): void
    {
        $this->originalData = ['path' => '/about us'];
        $validator = new RoutePathCharactersValidator();
        $validator->initialize($this->context);
        $this->setObject((new Route())->setPath('/about us'));

        $validator->validate('/about us', new RoutePathCharacters());

        $this->buildViolation('A path cannot contain whitespace, "?" or "#".')
            ->setInvalidValue('/about us')
            ->setCode(RoutePathCharacters::FORBIDDEN_CHARACTER_ERROR)
            ->assertRaised();
    }

    public function test_another_constraint_is_refused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('/about', new NotBlank());
    }

    public function test_a_value_that_is_not_a_string_is_refused(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(null, new RoutePathCharacters());
    }
}
