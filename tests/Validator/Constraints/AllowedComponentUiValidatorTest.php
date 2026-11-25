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

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\AbstractDummyAppComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Validator\Constraints\AllowedComponentUi;
use Silverback\ApiComponentsBundle\Validator\Constraints\AllowedComponentUiValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AllowedComponentUiValidator>
 */
final class AllowedComponentUiValidatorTest extends ConstraintValidatorTestCase
{
    private const string DUMMY = '/component/dummy_components';

    /** @var list<array{string, int, Operation|null}> */
    private array $iriCalls = [];

    private ?string $failingClass = null;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $names = $this->createStub(ResourceNameCollectionFactoryInterface::class);
        $names->method('create')->willReturn(new ResourceNameCollection([Page::class, AbstractComponent::class, AbstractDummyAppComponent::class, DummyComponent::class]));
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(function (object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null): string {
            \assert(\is_string($resource));
            $this->iriCalls[] = [$resource, $referenceType, $operation];
            if ($resource === $this->failingClass) {
                throw new InvalidArgumentException('No collection operation.');
            }

            return [Page::class => '/_/pages', AbstractComponent::class => '/abstract_components', AbstractDummyAppComponent::class => '/component/abstract_dummy_app_components', DummyComponent::class => self::DUMMY][$resource];
        });

        return new AllowedComponentUiValidator($names, $iriConverter);
    }

    public function test_a_component_resource_with_no_collection_iri_matches_no_key(): void
    {
        $this->failingClass = DummyComponent::class;
        $constraint = new AllowedComponentUi();

        $this->validator->validate([self::DUMMY => ['ui' => ['X']]], $constraint);

        $this->buildViolation($constraint->keyMessage)->setParameter('{{ key }}', self::DUMMY)->assertRaised();
    }

    public function test_null_is_valid_and_lists_no_resources(): void
    {
        $this->validator->validate(null, new AllowedComponentUi());

        $this->assertNoViolation();
        self::assertSame([], $this->iriCalls);
    }

    public function test_the_keys_are_compared_with_the_collection_iris_of_concrete_component_resources(): void
    {
        $this->validator->validate([self::DUMMY => ['ui' => [null, 'CwaHero'], 'styles' => ['style:large']]], new AllowedComponentUi());

        $this->assertNoViolation();
        self::assertCount(1, $this->iriCalls);
        [$resource, $referenceType, $operation] = $this->iriCalls[0];
        self::assertSame(DummyComponent::class, $resource);
        self::assertSame(UrlGeneratorInterface::ABS_PATH, $referenceType);
        self::assertInstanceOf(GetCollection::class, $operation);
        self::assertSame(DummyComponent::class, $operation->getClass());
    }

    public function test_a_rule_may_restrict_only_the_ui_or_only_the_styles(): void
    {
        $this->validator->validate([self::DUMMY => ['ui' => ['CwaHero']]], new AllowedComponentUi());
        $this->validator->validate([self::DUMMY => ['styles' => ['style:large']]], new AllowedComponentUi());

        $this->assertNoViolation();
    }

    /**
     * @param array<mixed> $value
     */
    #[DataProvider('invalidKeys')]
    public function test_a_key_that_is_not_the_collection_iri_of_a_concrete_component_resource_is_refused(array $value, string $key): void
    {
        $constraint = new AllowedComponentUi();
        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->keyMessage)->setParameter('{{ key }}', $key)->assertRaised();
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'a resource that is not a component' => [['/_/pages' => ['ui' => ['X']]], '/_/pages'];
        yield 'the abstract component resource' => [['/abstract_components' => ['ui' => ['X']]], '/abstract_components'];
        yield 'an abstract component subclass' => [['/component/abstract_dummy_app_components' => ['ui' => ['X']]], '/component/abstract_dummy_app_components'];
        yield 'a list index' => [[['ui' => ['X']]], '0'];
    }

    /**
     * @param array<mixed> $rule
     */
    #[DataProvider('invalidRules')]
    public function test_a_rule_that_is_not_an_object_of_ui_and_styles_is_refused(mixed $rule): void
    {
        $constraint = new AllowedComponentUi();
        $this->validator->validate([self::DUMMY => $rule], $constraint);

        $this->buildViolation($constraint->ruleMessage)->setParameter('{{ key }}', self::DUMMY)->assertRaised();
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidRules(): iterable
    {
        yield 'a string' => ['CwaHero'];
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'a list' => [['CwaHero']];
        yield 'another key beside ui' => [['ui' => ['CwaHero'], 'other' => ['X']]];
    }

    #[DataProvider('invalidUi')]
    public function test_ui_that_is_not_a_non_empty_list_of_strings_or_nulls_is_refused(mixed $ui): void
    {
        $constraint = new AllowedComponentUi();
        $this->validator->validate([self::DUMMY => ['ui' => $ui, 'styles' => ['style:large']]], $constraint);

        $this->buildViolation($constraint->uiMessage)->setParameter('{{ key }}', self::DUMMY)->assertRaised();
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidUi(): iterable
    {
        yield 'a string' => ['CwaHero'];
        yield 'null' => [null];
        yield 'empty' => [[]];
        yield 'an object' => [['a' => 'CwaHero']];
        yield 'a number after a string' => [['CwaHero', 1]];
        yield 'a list' => [[['CwaHero']]];
    }

    #[DataProvider('invalidStyles')]
    public function test_styles_that_are_not_a_non_empty_list_of_strings_are_refused(mixed $styles): void
    {
        $constraint = new AllowedComponentUi();
        $this->validator->validate([self::DUMMY => ['ui' => [null], 'styles' => $styles]], $constraint);

        $this->buildViolation($constraint->stylesMessage)->setParameter('{{ key }}', self::DUMMY)->assertRaised();
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidStyles(): iterable
    {
        yield 'a string' => ['style:large'];
        yield 'empty' => [[]];
        yield 'an object' => [['a' => 'style:large']];
        yield 'a null after a string' => [['style:large', null]];
        yield 'a number' => [[1]];
    }

    public function test_both_a_bad_ui_and_bad_styles_are_reported(): void
    {
        $constraint = new AllowedComponentUi();
        $this->validator->validate([self::DUMMY => ['ui' => [], 'styles' => []]], $constraint);

        $this->buildViolation($constraint->uiMessage)->setParameter('{{ key }}', self::DUMMY)
            ->buildNextViolation($constraint->stylesMessage)->setParameter('{{ key }}', self::DUMMY)
            ->assertRaised();
    }

    public function test_every_rule_is_checked(): void
    {
        $constraint = new AllowedComponentUi();
        $this->validator->validate(['/_/pages' => ['ui' => ['X']], self::DUMMY => 'X', '/other' => []], $constraint);

        $this->buildViolation($constraint->keyMessage)->setParameter('{{ key }}', '/_/pages')
            ->buildNextViolation($constraint->ruleMessage)->setParameter('{{ key }}', self::DUMMY)
            ->buildNextViolation($constraint->keyMessage)->setParameter('{{ key }}', '/other')
            ->assertRaised();
    }
}
