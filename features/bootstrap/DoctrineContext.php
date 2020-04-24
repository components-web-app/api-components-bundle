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

namespace Silverback\ApiComponentBundle\Features\Bootstrap;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behatch\Context\RestContext as BehatchRestContext;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Silverback\ApiComponentBundle\Entity\Component\Form;
use Silverback\ApiComponentBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Form\NestedType;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Form\TestRepeatedType;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Form\TestType;

final class DoctrineContext implements Context
{
    private ManagerRegistry $doctrine;
    private RestContext $restContext;
    private ?BehatchRestContext $baseRestContext;
    private JWTTokenManagerInterface $jwtManager;
    private IriConverterInterface $iriConverter;
    private ObjectManager $manager;
    private SchemaTool $schemaTool;
    private array $classes;
    private string $cacheDir;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct(ManagerRegistry $doctrine, JWTTokenManagerInterface $jwtManager, IriConverterInterface $iriConverter, string $cacheDir)
    {
        $this->doctrine = $doctrine;
        $this->jwtManager = $jwtManager;
        $this->iriConverter = $iriConverter;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
        $this->cacheDir = $cacheDir;
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->baseRestContext = $scope->getEnvironment()->getContext(BehatchRestContext::class);
    }

    /**
     * @BeforeScenario
     */
    public function createDatabase(): void
    {
        $this->schemaTool->dropSchema($this->classes);
        $this->doctrine->getManager()->clear();
        $this->schemaTool->createSchema($this->classes);
    }

    private function login(BeforeScenarioScope $scope, array $roles = []): void
    {
        $user = new User();
        $user
            ->setRoles($roles)
            ->setUsername('admin@admin.com')
            ->setPassword('admin');
        $this->manager->persist($user);
        $this->manager->flush();
        $this->manager->clear();

        $token = $this->jwtManager->create($user);

        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', "Bearer $token");
    }

    /**
     * @BeforeScenario @loginAdmin
     */
    public function loginAdmin(BeforeScenarioScope $scope): void
    {
        $this->login($scope, ['ROLE_ADMIN']);
    }

    /**
     * @BeforeScenario @loginUser
     */
    public function loginUser(BeforeScenarioScope $scope): void
    {
        $this->login($scope, ['ROLE_USER']);
    }

    /**
     * @AfterScenario
     * @logout
     */
    public function logout(): void
    {
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', '');
    }

    /**
     * @BeforeScenario @createRegisterForm
     */
    public function createRegisterForm(BeforeScenarioScope $scope)
    {
        $form = new Form();
        $form->formType = UserRegisterType::class;
        $this->manager->persist($form);
        $this->manager->flush();
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->restContext->components['register_form'] = $this->iriConverter->getIriFromItem($form);
    }

    /**
     * @BeforeScenario @createTestForm
     */
    public function createTestForm(BeforeScenarioScope $scope): void
    {
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $form = new Form();
        $form->formType = TestType::class;
        $this->manager->persist($form);
        $this->manager->flush();
        $this->restContext->components['test_form'] = $this->iriConverter->getIriFromItem($form);
    }

    /**
     * @BeforeScenario @createNestedForm
     */
    public function createNestedForm(BeforeScenarioScope $scope): void
    {
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $form = new Form();
        $form->formType = NestedType::class;
        $this->manager->persist($form);
        $this->manager->flush();
        $this->restContext->components['nested_form'] = $this->iriConverter->getIriFromItem($form);
    }

    /**
     * @BeforeScenario @createTestRepeatedForm
     */
    public function createTestRepeatedForm(BeforeScenarioScope $scope): void
    {
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $form = new Form();
        $form->formType = TestRepeatedType::class;
        $this->manager->persist($form);
        $this->manager->flush();
        $this->restContext->components['test_repeated_form'] = $this->iriConverter->getIriFromItem($form);
    }

    /**
     * @Given a user exists with the username :username password :password and role :role
     */
    public function aUserExistsWithUsernamePasswordAndRole(string $username, string $password, string $role)
    {
        $user = new User();
        $user
            ->setUsername($username)
            ->setEmailAddress('test.user@example.com')
            ->setPassword($password)
            ->setRoles([$role]);
        $this->manager->persist($user);
        $this->manager->flush();
        $this->restContext->components['user'] = $this->iriConverter->getIriFromItem($user);
    }

    /**
     * @Then the component :name should not exist
     */
    public function theComponentShouldNotExist(string $name)
    {
        try {
            $iri = $this->restContext->components[$name];
            $this->iriConverter->getItemFromIri($iri);
            throw new ExpectationException(sprintf('The component %s can still be found and has not been removed', $iri));
        } catch (ItemNotFoundException $exception) {
        }
    }

    /**
     * @Then the component :name should exist
     */
    public function theComponentShouldExist(string $name)
    {
        try {
            $iri = $this->restContext->components[$name];
            $this->iriConverter->getItemFromIri($iri);
        } catch (ItemNotFoundException $exception) {
            throw new ExpectationException(sprintf('The component %s cannot be found anymore', $iri));
        }
    }
}
