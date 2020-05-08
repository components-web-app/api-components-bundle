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
use PHPUnit\Framework\Assert;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyCustomTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestampedWithSerializationGroups;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\NestedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestRepeatedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

final class DoctrineContext implements Context
{
    private ManagerRegistry $doctrine;
    private RestContext $restContext;
    private ?BehatchRestContext $baseRestContext;
    private ?MinkContext $minkContext;
    private JWTTokenManagerInterface $jwtManager;
    private IriConverterInterface $iriConverter;
    private TimestampedDataPersister $timestampedHelper;
    private ObjectManager $manager;
    private SchemaTool $schemaTool;
    private UserPasswordEncoderInterface $passwordEncoder;
    private array $classes;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct(ManagerRegistry $doctrine, JWTTokenManagerInterface $jwtManager, IriConverterInterface $iriConverter, TimestampedDataPersister $timestampedHelper, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->doctrine = $doctrine;
        $this->jwtManager = $jwtManager;
        $this->iriConverter = $iriConverter;
        $this->timestampedHelper = $timestampedHelper;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
        $this->passwordEncoder = $passwordEncoder;
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
            ->setUsername('user@example.com')
            ->setPassword($this->passwordEncoder->encodePassword($user, 'password'))
            ->setEnabled(true)
            ->setEmailAddressVerified(true);
        $this->timestampedHelper->persistTimestampedFields($user, true);
        $this->manager->persist($user);
        $this->manager->flush();
        $this->manager->clear();

        $token = $this->jwtManager->create($user);
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', "Bearer $token");
        $this->restContext->components['login_user'] = $this->iriConverter->getIriFromItem($user);
    }

    /**
     * @BeforeScenario @loginSuperAdmin
     */
    public function loginSuperAdmin(BeforeScenarioScope $scope): void
    {
        $this->login(['ROLE_SUPER_ADMIN']);
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
            case 'password_update':
                $form->formType = PasswordUpdateType::class;
                break;
            case 'change_password':
                $form->formType = ChangePasswordType::class;
                break;
            case 'new_email':
                $form->formType = NewEmailAddressType::class;
                break;
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
        $this->timestampedHelper->persistTimestampedFields($form, true);
        $this->manager->persist($form);
        $this->manager->flush();
        $this->restContext->components[$type . '_form'] = $this->iriConverter->getIriFromItem($form);
    }

    /**
     * @Given there is a user with the username :username password :password and role :role
     */
    public function thereIsAUserWithUsernamePasswordAndRole(string $username, string $password, string $role): void
    {
        $user = new User();
        $user
            ->setUsername($username)
            ->setEmailAddress('test.user@example.com')
            ->setPassword($this->passwordEncoder->encodePassword($user, $password))
            ->setRoles([$role])
            ->setEnabled(false);
        $this->timestampedHelper->persistTimestampedFields($user, true);
        $this->manager->persist($user);
        $this->manager->flush();
        $this->restContext->components['user'] = $this->iriConverter->getIriFromItem($user);
    }

    /**
     * @Given the user has the newPasswordConfirmationToken :token requested at :dateTime
     */
    public function theUserHasTheNewPasswordConfirmationToken(string $token, string $dateTime): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getItemFromIri($this->restContext->components['user']);
        $user->setNewPasswordConfirmationToken($token)->setPasswordRequestedAt(new \DateTime($dateTime));
        $this->manager->flush();
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
        $this->timestampedHelper->persistTimestampedFields($component, true);
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->components['dummy_custom_timestamped'] = $this->iriConverter->getIriFromItem($component);
    }

    /**
     * @Given there is a DummyTimestampedWithSerializationGroups resource
     */
    public function thereIsADummyTimestampedWithSerializationGroupsResource(): void
    {
        $component = new DummyTimestampedWithSerializationGroups();
        $this->restContext->getCachedNow();
        $this->timestampedHelper->persistTimestampedFields($component, true);
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->components['dummy_custom_timestamped'] = $this->iriConverter->getIriFromItem($component);
    }

    /**
     * @Then the component :name should not exist
     */
    public function theComponentShouldNotExist(string $name): void
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
    public function theComponentShouldExist(string $name): void
    {
        $this->manager->clear();
        try {
            $iri = $this->restContext->components[$name];
            $this->iriConverter->getItemFromIri($iri);
        } catch (ItemNotFoundException $exception) {
            throw new ExpectationException(sprintf('The component %s cannot be found anymore', $iri), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then the password should be :password for username :username
     */
    public function thePasswordShouldBeEqualTo(string $password, string $username): void
    {
        $repository = $this->manager->getRepository(User::class);
        /** @var AbstractUser $user */
        $user = $repository->findOneBy([
            'username' => $username,
        ]);
        Assert::assertTrue($this->passwordEncoder->isPasswordValid($user, $password));
    }
}
