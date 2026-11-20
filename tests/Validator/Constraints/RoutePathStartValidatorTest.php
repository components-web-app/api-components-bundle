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
use Silverback\ApiComponentsBundle\Annotation\RoutePrefix;
use Silverback\ApiComponentsBundle\AttributeReader\RoutePrefixAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RoutePrefixResolver;
use Silverback\ApiComponentsBundle\Validator\Constraints\RoutePathStart;
use Silverback\ApiComponentsBundle\Validator\Constraints\RoutePathStartValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class RoutePathStartValidatorTest extends ConstraintValidatorTestCase
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

        return new RoutePathStartValidator(
            new RoutePrefixResolver(new RoutePrefixAttributeReader($this->createStub(ManagerRegistry::class))),
            $registry,
        );
    }

    public function test_a_new_route_under_the_required_start_passes(): void
    {
        $this->validator->validate(self::childRoute('/conference/programme'), new RoutePathStart());

        $this->assertNoViolation();
    }

    public function test_a_new_route_several_segments_under_the_required_start_passes(): void
    {
        $this->validator->validate(self::childRoute('/conference/programme/day-one'), new RoutePathStart());

        $this->assertNoViolation();
    }

    public function test_a_new_route_outside_the_required_start_is_a_violation_on_path_naming_the_start(): void
    {
        $this->validator->validate(self::childRoute('/programme'), new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/programme')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_a_new_route_equal_to_the_required_start_is_a_violation(): void
    {
        $this->validator->validate(self::childRoute('/conference/'), new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/conference/')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_a_path_that_shares_the_start_text_without_its_slash_is_a_violation(): void
    {
        $this->validator->validate(self::childRoute('/conferences'), new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/conferences')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_the_constraints_message_is_used(): void
    {
        $constraint = new RoutePathStart();
        $constraint->message = 'Start with {{ start }}.';

        $this->validator->validate(self::childRoute('/programme'), $constraint);

        $this->buildViolation('Start with {{ start }}.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/programme')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_prefixed_page_data_at_the_root_must_start_with_its_prefix(): void
    {
        $route = self::route('/launch');
        $route->setPageData(new #[RoutePrefix('/announcements')] class extends AbstractPageData {});

        $this->validator->validate($route, new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/announcements/')
            ->atPath('property.path.path')
            ->setInvalidValue('/launch')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_a_root_page_may_have_any_path(): void
    {
        $route = self::route('/anything/at/all');
        $route->setPage(new Page());

        $this->validator->validate($route, new RoutePathStart());

        $this->assertNoViolation();
    }

    public function test_a_pure_redirect_is_not_checked(): void
    {
        $route = self::route('/old');
        $route->setRedirect(self::route('/conference/new'));

        $this->validator->validate($route, new RoutePathStart());

        $this->assertNoViolation();
    }

    public function test_a_redirect_holding_page_data_is_checked(): void
    {
        $route = self::childRoute('/programme');
        $route->setRedirect(self::route('/conference/programme'));

        $this->validator->validate($route, new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/programme')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_a_stored_route_whose_path_and_page_are_unchanged_is_not_checked(): void
    {
        $route = self::childRoute('/programme');
        $this->originalData = ['path' => '/programme', 'page' => $route->getPage(), 'pageData' => null];

        $this->validator->validate($route, new RoutePathStart());

        $this->assertNoViolation();
    }

    public function test_a_stored_route_whose_path_changed_is_checked(): void
    {
        $route = self::childRoute('/programme');
        $this->originalData = ['path' => '/conference/programme', 'page' => $route->getPage(), 'pageData' => null];

        $this->validator->validate($route, new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/programme')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_a_stored_route_whose_page_changed_is_checked(): void
    {
        $route = self::childRoute('/programme');
        $this->originalData = ['path' => '/programme', 'page' => null, 'pageData' => null];

        $this->validator->validate($route, new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/programme')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_a_stored_route_whose_page_data_changed_is_checked(): void
    {
        $parent = (new Page())->setRoute(self::route('/conference'));
        $pageData = (new class extends AbstractPageData {})->setParentPage($parent);
        $route = self::route('/programme');
        $route->setPageData($pageData);
        $this->originalData = ['path' => '/programme', 'page' => null, 'pageData' => null];

        $this->validator->validate($route, new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/programme')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_the_page_is_checked_when_a_route_holds_both_a_page_and_page_data(): void
    {
        $route = self::childRoute('/conference/programme');
        $route->setPageData(new #[RoutePrefix('/announcements')] class extends AbstractPageData {});

        $this->validator->validate($route, new RoutePathStart());

        $this->assertNoViolation();
    }

    public function test_a_route_with_no_orm_manager_is_checked_as_new(): void
    {
        $route = self::childRoute('/programme');
        $this->originalData = ['path' => '/programme', 'page' => $route->getPage(), 'pageData' => null];
        $this->manager = $this->createStub(ObjectManager::class);

        $this->validator->validate($route, new RoutePathStart());

        $this->buildViolation('This path must start with "{{ start }}" and continue after it.')
            ->setParameter('{{ start }}', '/conference/')
            ->atPath('property.path.path')
            ->setInvalidValue('/programme')
            ->setCode(RoutePathStart::PATH_OUTSIDE_START_ERROR)
            ->assertRaised();
    }

    public function test_another_constraint_is_refused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(self::childRoute('/conference/programme'), new NotBlank());
    }

    public function test_a_value_that_is_not_a_route_is_refused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Page(), new RoutePathStart());
    }

    public function test_the_constraint_is_a_class_constraint(): void
    {
        self::assertSame(Constraint::CLASS_CONSTRAINT, (new RoutePathStart())->getTargets());
    }

    private static function childRoute(string $path): Route
    {
        $parent = (new Page())->setRoute(self::route('/conference'));
        $route = self::route($path);
        $route->setPage((new Page())->setParentPage($parent));

        return $route;
    }

    private static function route(string $path): Route
    {
        return (new Route())->setName($path)->setPath($path);
    }
}
