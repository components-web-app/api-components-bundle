<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Behatch\Context\RestContext as BehatchRestContext;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyCustomTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\NestedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestRepeatedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;

final class DoctrineContext implements Context
{
    private ManagerRegistry $doctrine;
    private RestContext $restContext;
    private ?BehatchRestContext $baseRestContext;
    private ?MinkContext $minkContext;
    private JWTTokenManagerInterface $jwtManager;
    private IriConverterInterface $iriConverter;
    private ObjectManager $manager;
    private SchemaTool $schemaTool;
    private array $classes;

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
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->baseRestContext = $scope->getEnvironment()->getContext(BehatchRestContext::class);
        $this->minkContext = $scope->getEnvironment()->getContext(MinkContext::class);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
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

    private function login(array $roles = []): void
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
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', "Bearer $token");
    }

    /**
     * @BeforeScenario @loginAdmin
     */
    public function loginAdmin(BeforeScenarioScope $scope): void
    {
        $this->login(['ROLE_ADMIN']);
    }

    /**
     * @BeforeScenario @loginUser
     */
    public function loginUser(BeforeScenarioScope $scope): void
    {
        $this->login(['ROLE_USER']);
    }

    /**
     * @AfterScenario
     */
    public function logout(): void
    {
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', '');
    }

    /**
     * @Given there is a :type form
     */
    public function createForm(string $type)
    {
        $form = new Form();
        switch ($type) {
            case 'register':
                $form->formType = UserRegisterType::class;
                break;
            case 'test':
                $form->formType = TestType::class;
                break;
            case 'nested':
                $form->formType = NestedType::class;
                break;
            case 'test_repeated':
                $form->formType = TestRepeatedType::class;
        }
        $this->manager->persist($form);
        $this->manager->flush();
        $this->restContext->components[$type . '_form'] = $this->iriConverter->getIriFromItem($form);
    }

    /**
     * @Given there is a user with the username :username password :password and role :role
     */
    public function thereIsAUserWithUsernamePasswordAndRole(string $username, string $password, string $role)
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
     * @Given there is a DummyComponent
     */
    public function thereIsADummyComponent()
    {
        $component = new DummyComponent();
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->components['dummy_component'] = $this->iriConverter->getIriFromItem($component);
    }

    /**
     * @Given there is a DummyCustomTimestamped resource
     */
    public function thereIsADummyCustomTimestampedResource(): void
    {
        $component = new DummyCustomTimestamped();
        $this->restContext->getCachedNow();
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->components['dummy_custom_timestamped'] = $this->iriConverter->getIriFromItem($component);
    }

    /**
     * @Then the component :name should not exist
     */
    public function theComponentShouldNotExist(string $name)
    {
        $this->manager->clear();
        try {
            $iri = $this->restContext->components[$name];
            $this->iriConverter->getItemFromIri($iri);
            throw new ExpectationException(sprintf('The component %s can still be found and has not been removed', $iri), $this->minkContext->getSession()->getDriver());
        } catch (ItemNotFoundException $exception) {
        }
    }

    /**
     * @Then the component :name should exist
     */
    public function theComponentShouldExist(string $name)
    {
        $this->manager->clear();
        try {
            $iri = $this->restContext->components[$name];
            $this->iriConverter->getItemFromIri($iri);
        } catch (ItemNotFoundException $exception) {
            throw new ExpectationException(sprintf('The component %s cannot be found anymore', $iri), $this->minkContext->getSession()->getDriver());
        }
    }
}
