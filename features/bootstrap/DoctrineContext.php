<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Behatch\Context\RestContext as BehatchRestContext;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserLoginType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportStore;
use Silverback\ApiComponentsBundle\Helper\Route\RouteLiveResolver;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyCustomTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestampedWithSerializationGroups;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUnguardedTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithParentTypedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RefreshToken;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedPageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\NestedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestRepeatedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
    private UserPasswordHasherInterface $passwordHasher;
    private JWTEncoderInterface $jwtEncoder;
    private JsonContext $jsonContext;
    private RouteLiveResolver $routeLiveResolver;
    private KernelInterface $kernel;
    private OrphanedResourceReportStore $orphanedResourceReportStore;
    private ?\Throwable $commandException = null;
    private PasswordHasherFactoryInterface $passwordHasherFactory;

    public function __construct(ManagerRegistry $doctrine, JWTTokenManagerInterface $jwtManager, IriConverterInterface $iriConverter, TimestampedDataPersister $timestampedHelper, UserPasswordHasherInterface $passwordHasher, JWTEncoderInterface $jwtEncoder, RouteLiveResolver $routeLiveResolver, KernelInterface $kernel, PasswordHasherFactoryInterface $passwordHasherFactory, OrphanedResourceReportStore $orphanedResourceReportStore)
    {
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->orphanedResourceReportStore = $orphanedResourceReportStore;
        $this->kernel = $kernel;
        $this->routeLiveResolver = $routeLiveResolver;
        $this->doctrine = $doctrine;
        $this->jwtManager = $jwtManager;
        $this->iriConverter = $iriConverter;
        $this->timestampedHelper = $timestampedHelper;
        $this->manager = $doctrine->getManager();
        $this->passwordHasher = $passwordHasher;
        $this->jwtEncoder = $jwtEncoder;
    }

    /**
     * @BeforeSuite
     */
    public static function prepareTestSuite(): void
    {
        exec('php tests/Functional/app/bin/console cache:clear --env=test --no-warmup');
        exec('php tests/Functional/app/bin/console doctrine:database:drop --force --env=test 2>/dev/null; true');
        exec('php tests/Functional/app/bin/console doctrine:database:create --env=test');
        exec('php tests/Functional/app/bin/console doctrine:schema:create --env=test');
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->baseRestContext = $scope->getEnvironment()->getContext(BehatchRestContext::class);
        $this->minkContext = $scope->getEnvironment()->getContext(MinkContext::class);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->jsonContext = $scope->getEnvironment()->getContext(JsonContext::class);
    }

    /**
     * @BeforeScenario
     */
    public function setupDatabase(): void
    {
        StaticDriver::setKeepStaticConnections(true);
        StaticDriver::beginTransaction();
        $this->manager->clear();
    }

    /**
     * @AfterScenario
     */
    public function rollbackDatabase(): void
    {
        StaticDriver::rollBack();
        $this->manager->clear();
    }

    private function login(array $roles = [], $useAuthHeader = false): void
    {
        $user = new User();
        $user
            ->setRoles($roles)
            ->setUsername('new_user')
            ->setEmailAddress('user@example.com')
            ->setPassword($this->passwordHasher->hashPassword($user, 'password'))
            ->setEnabled(true)
            ->setEmailAddressVerified(true);
        $this->timestampedHelper->persistTimestampedFields($user, true);
        $this->manager->persist($user);
        $this->manager->flush();

        $token = $this->jwtManager->create($user);
        if ($useAuthHeader) {
            $this->baseRestContext->iAddHeaderEqualTo('Authorization', "Bearer $token");
        } else {
            $this->minkContext->getSession()->setCookie('api_components', $token);
        }
        $this->restContext->resources['login_user'] = $this->iriConverter->getIriFromResource($user);
        $this->manager->clear();
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
        $this->login(['ROLE_USER'], true);
    }

    /**
     * @AfterScenario
     */
    public function logout(): void
    {
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', '');
    }

    /**
     * @Given I add the logged in user's token to the request
     */
    public function iAddTheLoggedInUsersTokenToTheRequest(): void
    {
        $this->manager->clear();
        $user = $this->iriConverter->getResourceFromIri($this->restContext->resources['login_user']);
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', 'Bearer ' . $this->jwtManager->create($user));
    }

    /**
     * @Given the logged in user has been deleted from the database
     */
    public function deleteLoggedInUser(): void
    {
        $userIri = $this->restContext->resources['login_user'] ?? null;
        if (!$userIri) {
            throw new \RuntimeException('No logged in user resource found. Use a @loginAdmin or @loginSuperAdmin tag.');
        }
        $user = $this->iriConverter->getResourceFromIri($userIri);
        $this->manager->remove($user);
        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Given the logged in user has been recreated with the same username
     */
    public function theLoggedInUserHasBeenRecreatedWithSameUsername(): void
    {
        $user = new User();
        $user
            ->setRoles(['ROLE_ADMIN'])
            ->setUsername('new_user')
            ->setEmailAddress('recreated@example.com')
            ->setPassword($this->passwordHasher->hashPassword($user, 'password'))
            ->setEnabled(true)
            ->setEmailAddressVerified(true);
        $this->timestampedHelper->persistTimestampedFields($user, true);
        $this->manager->persist($user);
        $this->manager->flush();
    }

    /**
     * @Given there is a :type form
     */
    public function createForm(string $type)
    {
        $form = new Form();
        switch ($type) {
            case 'login':
                $form->formType = UserLoginType::class;
                break;
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
        $this->restContext->resources[$type . '_form'] = $this->iriConverter->getIriFromResource($form);
    }

    /**
     * @Given /^there is a user with the username "([^" ]*)" password "([^" ]*)" and role "([^" ]*)"(?: and the email address "([^" ]*)"|)$/i
     */
    public function thereIsAUserWithUsernamePasswordAndRole(string $username, string $password, string $role, string $emailAddress = 'test.user@example.com'): void
    {
        $user = new User();
        $user
            ->setUsername($username)
            ->setEmailAddress($emailAddress)
            ->setPassword($this->passwordHasher->hashPassword($user, $password))
            ->setRoles([$role])
            ->setEnabled(true)
            ->setEmailAddressVerified(true);
        $this->timestampedHelper->persistTimestampedFields($user, true);
        $this->manager->persist($user);
        $this->manager->flush();
        $this->restContext->resources['user'] = $this->iriConverter->getIriFromResource($user);
    }

    /**
     * @Given the user has the newPasswordConfirmationToken :token requested at :dateTime
     */
    public function theUserHasTheNewPasswordConfirmationToken(string $token, string $dateTime): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getResourceFromIri($this->restContext->resources['user']);
        $user->setNewPasswordConfirmationToken($this->passwordHasher->hashPassword($user, $token))->setPasswordRequestedAt(new \DateTime($dateTime));
        $this->manager->flush();
    }

    /**
     * @Given the user is disabled
     */
    public function theUserIsDisabled(): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getResourceFromIri($this->restContext->resources['user']);
        $user->setEnabled(false);
        $this->manager->flush();
    }

    /**
     * @Given /^the user email is not verified(?: with the token "([^"]+)"|)$/
     */
    public function theUserEmailIsNotVerified(?string $verificationToken = null): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getResourceFromIri($this->restContext->resources['user']);
        $user->setEmailAddressVerified(false);
        if ($verificationToken) {
            $user->setEmailAddressVerifyToken($this->passwordHasher->hashPassword($user, $verificationToken));
        }
        $this->manager->flush();
    }

    /**
     * @Given the user email verification was requested at :dateTime
     */
    public function theUserEmailVerificationWasRequestedAt(string $dateTime): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getResourceFromIri($this->restContext->resources['user']);
        $user->setEmailAddressVerificationRequestedAt(new \DateTime($dateTime));
        $this->manager->flush();
    }

    /**
     * @Given the logged in user has a new email address :emailAddress and confirmation token :token and the email was sent at :emailSentAt
     */
    public function theLoggedInUserHasANewEmailAddress(string $emailAddress, string $token, string $emailSentAt): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getResourceFromIri($this->restContext->resources['login_user']);
        $user
            ->setNewEmailAddress($emailAddress)
            ->setNewEmailConfirmationToken($this->passwordHasher->hashPassword($user, $token))
            ->setNewEmailAddressChangeRequestedAt(new \DateTime($emailSentAt));
        $this->manager->flush();
    }

    /**
     * @Given /^the user has a new email address "([^" ]*)" and confirmation token "([^" ]*)"(?: and the email was sent at "([^"]*)"|)$/i
     */
    public function theUserHasANewEmailAddress(string $emailAddress, string $verificationToken, string $emailSentAt = 'now'): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getResourceFromIri($this->restContext->resources['user']);
        $user
            ->setNewEmailAddress($emailAddress)
            ->setNewEmailConfirmationToken($this->passwordHasher->hashPassword($user, $verificationToken))
            ->setNewEmailAddressChangeRequestedAt(new \DateTime($emailSentAt));
        $this->manager->flush();
    }

    /**
     * @Given there is a DummyComponent
     */
    public function thereIsADummyComponent(): DummyComponent
    {
        $component = new DummyComponent();
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->resources['dummy_component'] = $this->iriConverter->getIriFromResource($component);

        return $component;
    }

    /**
     * @Given there is a DummyPublishableComponent
     */
    public function thereIsADummyPublishableComponent(): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->resources['dummy_publishable_component'] = $this->iriConverter->getIriFromResource($component);

        return $component;
    }

    /**
     * @Given there is a RestrictedComponent
     */
    public function thereIsARestrictedComponent(): void
    {
        $component = new RestrictedComponent();
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->resources['restricted_component'] = $this->iriConverter->getIriFromResource($component);
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
        $this->restContext->resources['dummy_custom_timestamped'] = $this->iriConverter->getIriFromResource($component);
    }

    /**
     * @Given there is a DummyTimestamped resource created at :createdAt
     */
    public function thereIsADummyTimestampedResourceCreatedAt(string $createdAt): void
    {
        $component = new DummyTimestamped();
        $component->createdAt = new \DateTimeImmutable($createdAt);
        $component->modifiedAt = new \DateTime($createdAt);
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->resources['dummy_timestamped'] = $this->iriConverter->getIriFromResource($component);
    }

    /**
     * @Given there is a DummyUnguardedTimestamped resource created at :createdAt
     */
    public function thereIsADummyUnguardedTimestampedResourceCreatedAt(string $createdAt): void
    {
        $component = new DummyUnguardedTimestamped();
        $component->createdAt = new \DateTimeImmutable($createdAt);
        $component->modifiedAt = new \DateTime($createdAt);
        $this->manager->persist($component);
        $this->manager->flush();
        $this->restContext->resources['dummy_unguarded_timestamped'] = $this->iriConverter->getIriFromResource($component);
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
        $this->restContext->resources['dummy_custom_timestamped'] = $this->iriConverter->getIriFromResource($component);
    }

    /**
     * @Given /^there is a ComponentGroup in a Page$/
     */
    public function thereIsAComponentGroupInAPage()
    {
        $page = $this->thereIsAPage();
        $group = $this->thereIsAComponentGroupWithComponents(1);
        $page->addComponentGroup($group);
        $this->manager->persist($page);
        $this->manager->flush();
    }

    /**
     * @Given there is a valid Page with a ComponentGroup
     */
    public function thereIsAValidPageWithAComponentGroup(): void
    {
        $layout = $this->thereIsALayout();

        $page = new Page();
        $page->reference = 'page-with-group';
        $page->uiComponent = 'TestComponent';
        $page->isTemplate = false;
        $page->layout = $layout;
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $group = $this->thereIsAComponentGroupWithComponents(1);
        $page->addComponentGroup($group);
        $this->manager->persist($page);
        $this->manager->flush();

        $this->restContext->resources['page'] = $this->iriConverter->getIriFromResource($page);
    }

    /**
     * @Given there is a routed Page with a component group and a component with the path :path
     */
    public function thereIsARoutedPageWithAComponentGroupAndComponent(string $path): void
    {
        $layout = $this->thereIsALayout('manifest-tag-layout');

        $page = new Page();
        $page->reference = 'manifest-tag-page';
        $page->uiComponent = 'TestComponent';
        $page->isTemplate = false;
        $page->layout = $layout;
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'manifest-tag-group';
        $componentGroup->location = 'manifest-tag-group';
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);
        $page->addComponentGroup($componentGroup);

        $component = new DummyComponent();
        $this->manager->persist($component);

        $position = new ComponentPosition();
        $position->componentGroup = $componentGroup;
        $position->component = $component;
        $position->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($position, true);
        $this->manager->persist($position);

        $route = new Route();
        $route->setPath($path)->setName($path)->setPage($page);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        $this->manager->flush();

        $this->restContext->resources['route'] = $this->iriConverter->getIriFromResource($route);
        $this->restContext->resources['page'] = $this->iriConverter->getIriFromResource($page);
        $this->restContext->resources['component_group'] = $this->iriConverter->getIriFromResource($componentGroup);
        $this->restContext->resources['component'] = $this->iriConverter->getIriFromResource($component);
        $this->restContext->resources['position'] = $this->iriConverter->getIriFromResource($position);
        $this->restContext->resources['page_manifest'] = '/_/resource_manifest/' . $page->getId();
    }

    /**
     * @Given /^there is a ComponentGroup in a Page and a Layout$/
     */
    public function thereIsAComponentGroupInAPageAndALayout()
    {
        $page = $this->thereIsAPage();
        $layout = $this->thereIsALayout();
        $group = $this->thereIsAComponentGroupWithComponents(1);
        $page->addComponentGroup($group);
        $layout->addComponentGroup($group);
        $this->manager->persist($layout);
        $this->manager->persist($page);
        $this->manager->flush();
    }

    /**
     * @Given /^there is a ComponentGroup with (\d+) components(?:| and the ID "([^"]+)")$/
     */
    public function thereIsAComponentGroupWithComponents(int $count, ?string $id = null, string $collectionReference = 'collection'): ComponentGroup
    {
        return $this->createComponentGroupWithComponents($count, $id, $collectionReference);
    }

    /**
     * @Given there is another ComponentGroup with :count components
     */
    public function thereIsAnotherComponentGroupWithComponents(int $count): ComponentGroup
    {
        return $this->createComponentGroupWithComponents($count, null, 'other_collection', 'other_');
    }

    /**
     * @Given the component :name has a ComponentGroup :prefix holding a component
     */
    public function theComponentHasAComponentGroupHoldingAComponent(string $name, string $prefix): void
    {
        /** @var AbstractComponent $component */
        $component = $this->iriConverter->getResourceFromIri($this->restContext->resources[$name]);
        $group = $this->createComponentGroupWithComponents(1, null, $prefix, $prefix . '_');
        $component->addComponentGroup($group);
        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Given the Page :name has a ComponentGroup :prefix holding a component
     */
    public function thePageHasAComponentGroupHoldingAComponent(string $name, string $prefix): void
    {
        /** @var Page $page */
        $page = $this->iriConverter->getResourceFromIri($this->restContext->resources[$name]);
        $group = $this->createComponentGroupWithComponents(1, null, $prefix, $prefix . '_');
        $page->addComponentGroup($group);
        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Given the component :name has a ComponentGroup :prefix which holds the component itself
     */
    public function theComponentHasAComponentGroupHoldingItself(string $name, string $prefix): void
    {
        /** @var AbstractComponent $component */
        $component = $this->iriConverter->getResourceFromIri($this->restContext->resources[$name]);
        $group = new ComponentGroup();
        $group->reference = $prefix;
        $group->location = $prefix;
        $this->timestampedHelper->persistTimestampedFields($group, true);
        $this->manager->persist($group);
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->component = $component;
        $position->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($position, true);
        $this->manager->persist($position);
        $component->addComponentGroup($group);
        $this->manager->flush();
        $this->restContext->resources[$prefix . '_component_group'] = $this->iriConverter->getIriFromResource($group);
        $this->restContext->resources[$prefix . '_position'] = $this->iriConverter->getIriFromResource($position);
        $this->manager->clear();
    }

    /**
     * @Given the Pages :names use a Layout with a ComponentGroup holding a component
     */
    public function thePagesUseALayoutWithAComponentGroupHoldingAComponent(string $names): void
    {
        $layout = $this->thereIsALayout('shared-layout');
        $group = $this->createComponentGroupWithComponents(1, null, 'layout_top', 'layout_');
        $layout->addComponentGroup($group);
        foreach (array_map('trim', explode(',', $names)) as $name) {
            /** @var Page $page */
            $page = $this->iriConverter->getResourceFromIri($this->restContext->resources[$name]);
            $page->layout = $layout;
        }
        $this->manager->flush();
        $this->manager->clear();
        $this->restContext->resources['shared_layout'] = $this->restContext->resources['layout'];
    }

    /**
     * @Given the ComponentPosition :name has the sortValue :sortValue
     */
    public function theComponentPositionHasTheSortValue(string $name, int $sortValue): void
    {
        /** @var ComponentPosition $position */
        $position = $this->iriConverter->getResourceFromIri($this->restContext->resources[$name]);
        $position->sortValue = $sortValue;
        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Then the ComponentPosition sort values should be:
     */
    public function theComponentPositionSortValuesShouldBe(TableNode $table): void
    {
        $this->manager->clear();
        $mismatches = [];
        foreach ($table->getHash() as $row) {
            /** @var ComponentPosition $position */
            $position = $this->iriConverter->getResourceFromIri($this->restContext->resources[$row['position']]);
            if ($position->sortValue !== (int) $row['sortValue']) {
                $mismatches[] = \sprintf('%s: expected %s, got %s', $row['position'], $row['sortValue'], var_export($position->sortValue, true));
            }
        }
        if ($mismatches) {
            throw new ExpectationException(implode('; ', $mismatches), $this->minkContext->getSession()->getDriver());
        }
    }

    private function createComponentGroupWithComponents(int $count, ?string $id, string $collectionReference, string $prefix = ''): ComponentGroup
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = $collectionReference;
        $componentGroup->location = $collectionReference;
        $componentGroup->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->manager->persist($componentGroup);
        if ($id) {
            $reflection = new \ReflectionClass($componentGroup);
            $reflectionProp = $reflection->getProperty('id');
            $reflectionProp->setValue($componentGroup, Uuid::fromString($id));
            $this->manager->flush();
            $repo = $this->manager->getRepository(ComponentGroup::class);
            $componentGroup = $repo->find($id);
        }

        for ($x = 0; $x < $count; ++$x) {
            $component = new DummyComponent();
            $this->manager->persist($component);
            $position = new ComponentPosition();
            $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
            $position->sortValue = $x;
            $position->componentGroup = $componentGroup;
            $position->component = $component;
            $this->manager->persist($position);
            $this->restContext->resources[$prefix . 'component_' . $x] = $this->iriConverter->getIriFromResource($component);
            $this->restContext->resources[$prefix . 'position_' . $x] = $this->iriConverter->getIriFromResource($position);
        }
        $this->manager->flush();

        $this->restContext->resources[$prefix . 'component_group'] = $this->iriConverter->getIriFromResource($componentGroup);

        return $componentGroup;
    }

    /**
     * @Given the ComponentGroup has the allowedComponent :allowedComponent
     */
    public function theComponentGroupHasTheAllowedComponents(string $allowedComponent): void
    {
        /** @var ComponentGroup $collection */
        $collection = $this->iriConverter->getResourceFromIri($this->restContext->resources['component_group']);
        if ('' !== $allowedComponent) {
            $collection->allowedComponents = [$allowedComponent];
        }
        $this->manager->persist($collection);
        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Given the ComponentGroup has a RestrictedComponent position with the sortValue :sortValue
     */
    public function theComponentGroupHasARestrictedComponentPositionWithTheSortValue(int $sortValue): void
    {
        /** @var ComponentGroup $componentGroup */
        $componentGroup = $this->iriConverter->getResourceFromIri($this->restContext->resources['component_group']);
        $component = new RestrictedComponent();
        $this->manager->persist($component);
        $position = new ComponentPosition();
        $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $position->sortValue = $sortValue;
        $position->componentGroup = $componentGroup;
        $position->component = $component;
        $this->manager->persist($position);
        $this->manager->flush();
        $this->manager->clear();
        $this->restContext->resources['restricted_position'] = $this->iriConverter->getIriFromResource($position);
    }

    /**
     * @Given the ComponentGroup has a DummyPublishableComponent position
     */
    public function theComponentGroupHasADummyPublishableComponentPosition(): void
    {
        /** @var ComponentGroup $collection */
        $collection = $this->iriConverter->getResourceFromIri($this->restContext->resources['component_group']);
        $component = new DummyPublishableComponent();
        $this->manager->persist($component);
        $position = new ComponentPosition();
        $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $position->sortValue = 999;
        $position->componentGroup = $collection;
        $position->component = $component;
        $this->manager->persist($position);
        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Given there is a page with parent page :parentRef
     */
    public function thereIsAPageWithParentPage(string $parentRef): void
    {
        /** @var Page $parent */
        $parent = $this->iriConverter->getResourceFromIri($this->restContext->resources[$parentRef]);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'child-page';
        $page->setParentPage($parent);
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);
        $this->manager->flush();
        $this->restContext->resources['child_page'] = $this->iriConverter->getIriFromResource($page);
    }

    /**
     * @Given there is a page data with parent page :parentRef
     */
    public function thereIsAPageDataWithParentPage(string $parentRef): void
    {
        /** @var Page $parent */
        $parent = $this->iriConverter->getResourceFromIri($this->restContext->resources[$parentRef]);

        $templatePage = new Page();
        $templatePage->isTemplate = true;
        $templatePage->reference = 'page-data-template';
        $this->timestampedHelper->persistTimestampedFields($templatePage, true);
        $this->manager->persist($templatePage);

        $pageData = new PageData();
        $pageData->page = $templatePage;
        $pageData->setParentPage($parent);
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);
        $this->manager->flush();
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);
    }

    /**
     * @Given there is a Page
     */
    public function thereIsAPage(string $reference = 'page'): Page
    {
        $page = new Page();
        $page->isTemplate = false;
        $page->reference = $reference;
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);
        $this->manager->flush();
        $this->restContext->resources['page'] = $this->iriConverter->getIriFromResource($page);

        return $page;
    }

    /**
     * @Given /^there is a(?: (template))? Page with the reference "([^"]+)"(?: and with createdAt "([^"]+)")?(?: and with the title "([^"]+)")?(?: and with the uiComponent "([^"]+)")?$/
     */
    public function thereIsAPageWithOptions(?string $isTemplate, string $reference, ?string $createdAt = null, ?string $title = null, ?string $uiComponent = null): Page
    {
        $page = new Page();
        $page->isTemplate = 'template' === $isTemplate;
        $page->reference = $reference;
        $page->setTitle($title);
        $page->uiComponent = $uiComponent;
        if (null !== $createdAt) {
            $page->setCreatedAt(new \DateTimeImmutable($createdAt));
        }
        $this->timestampedHelper->persistTimestampedFields($page, null === $createdAt);
        $this->manager->persist($page);
        $this->manager->flush();
        $this->restContext->resources[$reference] = $this->iriConverter->getIriFromResource($page);

        return $page;
    }

    private function buildReachabilityComponentGroup(string $reference): ComponentGroup
    {
        $component = new DummyComponent();
        $this->manager->persist($component);

        $componentGroup = new ComponentGroup();
        $componentGroup->reference = $reference;
        $componentGroup->location = $reference;
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);

        $position = new ComponentPosition();
        $position->componentGroup = $componentGroup;
        $position->component = $component;
        $position->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($position, true);
        $this->manager->persist($position);

        $this->restContext->resources['parent_component'] = $this->iriConverter->getIriFromResource($component);
        $this->restContext->resources['parent_component_group'] = $this->iriConverter->getIriFromResource($componentGroup);

        return $componentGroup;
    }

    private function buildRoutelessParentPageData(string $groupReference): PageData
    {
        $parentTemplate = new Page();
        $parentTemplate->isTemplate = true;
        $parentTemplate->reference = 'routeless parent template';
        $parentTemplate->addComponentGroup($this->buildReachabilityComponentGroup($groupReference));
        $this->timestampedHelper->persistTimestampedFields($parentTemplate, true);
        $this->manager->persist($parentTemplate);

        $parentPageData = new PageData();
        $parentPageData->page = $parentTemplate;
        $this->timestampedHelper->persistTimestampedFields($parentPageData, true);
        $this->manager->persist($parentPageData);

        $this->restContext->resources['parent_template'] = $this->iriConverter->getIriFromResource($parentTemplate);
        $this->restContext->resources['parent_page_data'] = $this->iriConverter->getIriFromResource($parentPageData);

        return $parentPageData;
    }

    private function buildChildPage(string $reference): Page
    {
        $childPage = new Page();
        $childPage->isTemplate = false;
        $childPage->reference = $reference;
        $this->timestampedHelper->persistTimestampedFields($childPage, true);
        $this->manager->persist($childPage);

        return $childPage;
    }

    private function buildRouteForPage(Page $page, string $path): Route
    {
        $route = new Route();
        $route->setPath($path)->setName($path)->setPage($page);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        return $route;
    }

    /**
     * @Given there is a routeless parent PageData with a component and a routed child Page with the path :path
     */
    public function thereIsARoutelessParentPageDataWithARoutedChild(string $path): void
    {
        $parentPageData = $this->buildRoutelessParentPageData('routeless parent group');

        $childPage = $this->buildChildPage('routed child page');
        $childPage->setParentPageData($parentPageData);
        $childRoute = $this->buildRouteForPage($childPage, $path);

        $this->manager->flush();

        $this->restContext->resources['child_page'] = $this->iriConverter->getIriFromResource($childPage);
        $this->restContext->resources['child_route'] = $this->iriConverter->getIriFromResource($childRoute);
        $this->restContext->resources['parent_manifest'] = '/_/resource_manifest/' . $parentPageData->getId();
        $this->manager->clear();
    }

    /**
     * @Given there is a routeless Page with a component and no routed descendant
     */
    public function thereIsARoutelessPageWithNoRoutedDescendant(): void
    {
        $page = new Page();
        $page->isTemplate = false;
        $page->reference = 'orphan routeless page';
        $page->addComponentGroup($this->buildReachabilityComponentGroup('orphan group'));
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $this->manager->flush();

        $this->restContext->resources['orphan_page'] = $this->iriConverter->getIriFromResource($page);
        $this->manager->clear();
    }

    /**
     * @Given there is a routeless parent PageData with a component and an unrouted child Page
     */
    public function thereIsARoutelessParentPageDataWithAnUnroutedChild(): void
    {
        $parentPageData = $this->buildRoutelessParentPageData('unreachable parent group');

        $childPage = $this->buildChildPage('unrouted child page');
        $childPage->setParentPageData($parentPageData);

        $this->manager->flush();

        $this->restContext->resources['child_page'] = $this->iriConverter->getIriFromResource($childPage);
        $this->manager->clear();
    }

    /**
     * @Given there is a routeless parent Page with a component and a routed child Page with the path :path
     */
    public function thereIsARoutelessParentPageWithARoutedChild(string $path): void
    {
        $parentPage = new Page();
        $parentPage->isTemplate = false;
        $parentPage->reference = 'routeless parent page';
        $parentPage->addComponentGroup($this->buildReachabilityComponentGroup('routeless parent page group'));
        $this->timestampedHelper->persistTimestampedFields($parentPage, true);
        $this->manager->persist($parentPage);

        $childPage = $this->buildChildPage('routed child page');
        $childPage->setParentPage($parentPage);
        $this->buildRouteForPage($childPage, $path);

        $this->manager->flush();

        $this->restContext->resources['parent_page'] = $this->iriConverter->getIriFromResource($parentPage);
        $this->manager->clear();
    }

    /**
     * @Given there is a chain of :depth routeless Pages ending in a routed Page with the path :path
     */
    public function thereIsAChainOfRoutelessPages(int $depth, string $path): void
    {
        $root = null;
        $parent = null;
        for ($i = 0; $i < $depth; ++$i) {
            $page = new Page();
            $page->isTemplate = false;
            $page->reference = 'chain page ' . $i;
            if (0 === $i) {
                $page->addComponentGroup($this->buildReachabilityComponentGroup('chain group'));
            }
            if (null !== $parent) {
                $page->setParentPage($parent);
            }
            $this->timestampedHelper->persistTimestampedFields($page, true);
            $this->manager->persist($page);
            $root ??= $page;
            $parent = $page;
        }

        $leaf = $this->buildChildPage('chain leaf');
        $leaf->setParentPage($parent);
        $this->buildRouteForPage($leaf, $path);

        $this->manager->flush();

        $this->restContext->resources['chain_root'] = $this->iriConverter->getIriFromResource($root);
        $this->manager->clear();
    }

    /**
     * @Given there are two routeless PageData resources which are each other's parent
     */
    public function thereAreTwoPageDataResourcesWhichAreEachOthersParent(): void
    {
        $pageOne = new Page();
        $pageOne->isTemplate = true;
        $pageOne->reference = 'cycle template one';
        $this->timestampedHelper->persistTimestampedFields($pageOne, true);
        $this->manager->persist($pageOne);

        $first = new PageData();
        $first->page = $pageOne;
        $this->timestampedHelper->persistTimestampedFields($first, true);
        $this->manager->persist($first);

        $second = new PageData();
        $second->page = $pageOne;
        $this->timestampedHelper->persistTimestampedFields($second, true);
        $this->manager->persist($second);

        $this->manager->flush();

        $first->setParentPageData($second);
        $second->setParentPageData($first);
        $this->manager->flush();

        $this->restContext->resources['cycle_page_data'] = $this->iriConverter->getIriFromResource($first);
        $this->manager->clear();
    }

    /**
     * @Given there is a routeless parent PageData with a dynamic position and a routed child Page with the path :path
     */
    public function thereIsARoutelessParentPageDataWithADynamicPosition(string $path): void
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'dynamic parent group';
        $componentGroup->location = 'dynamic parent group';
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);

        $position = new ComponentPosition();
        $position->pageDataProperty = 'component';
        $position->pageDataClass = PageDataWithComponent::class;
        $position->componentGroup = $componentGroup;
        $position->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($position, true);
        $this->manager->persist($position);

        $parentTemplate = new Page();
        $parentTemplate->isTemplate = true;
        $parentTemplate->reference = 'dynamic parent template';
        $parentTemplate->addComponentGroup($componentGroup);
        $this->timestampedHelper->persistTimestampedFields($parentTemplate, true);
        $this->manager->persist($parentTemplate);

        $parentPageData = new PageDataWithComponent();
        $parentPageData->component = $this->thereIsADummyComponent();
        $parentPageData->page = $parentTemplate;
        $this->timestampedHelper->persistTimestampedFields($parentPageData, true);
        $this->manager->persist($parentPageData);

        $childPage = $this->buildChildPage('dynamic routed child');
        $childPage->setParentPageData($parentPageData);
        $this->buildRouteForPage($childPage, $path);

        $this->manager->flush();

        $this->restContext->resources['parent_page_data'] = $this->iriConverter->getIriFromResource($parentPageData);
        $this->restContext->resources['parent_position'] = $this->iriConverter->getIriFromResource($position);
        $this->manager->clear();
    }

    /**
     * @Given there is a child Page with a Layout
     */
    public function thereIsAChildPageWithLayout(): Page
    {
        $layout = $this->thereIsALayout();

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'child';
        $page->layout = $layout;
        $page->uiComponent = 'myComponent';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);
        $this->manager->flush();
        $this->restContext->resources['child_page'] = $this->iriConverter->getIriFromResource($page);

        return $page;
    }

    /**
     * @Given there is a SiteConfigParameter
     */
    public function thereIsASiteConfigParameter(string $key = 'key', string $value = 'value'): SiteConfigParameter
    {
        $param = new SiteConfigParameter();
        $param->setKey($key)->setValue($value);
        $this->manager->persist($param);
        $this->manager->flush();
        $this->restContext->resources['site_config_param'] = $this->iriConverter->getIriFromResource($param);

        return $param;
    }

    /**
     * @Given /^there are (\d+) SiteConfigParameters$/
     */
    public function thereAreSiteConfigParameters(int $count): void
    {
        $params = [];
        for ($i = 0; $i < $count; ++$i) {
            $param = new SiteConfigParameter();
            $param->setKey(\sprintf('key_%d', $i))->setValue(\sprintf('value_%d', $i));
            $this->manager->persist($param);
            $params[$i] = $param;
        }
        $this->manager->flush();
        foreach ($params as $i => $param) {
            $this->restContext->resources[\sprintf('site_config_param_%d', $i)] = $this->iriConverter->getIriFromResource($param);
        }
    }

    /**
     * @Given /^there (?:is|are) (\d+) Route(?:s)?$/
     */
    public function thereAreRoutes(int $count): void
    {
        for ($x = 0; $x < $count; ++$x) {
            $route = new Route();
            $route
                ->setPath(\sprintf('/route-%s', $x))
                ->setName(\sprintf('/route-%s', $x));
            $this->timestampedHelper->persistTimestampedFields($route, true);
            $this->manager->persist($route);
            $this->restContext->resources['route_' . $x] = $this->iriConverter->getIriFromResource($route);
        }
        $this->manager->flush();
    }

    /**
     * @Given there is a Route :path with a page
     */
    public function thereIsARouteWithAPage(string $path): void
    {
        $route = new Route();
        $route
            ->setPath($path)
            ->setName($path);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        $page = new Page();
        $page->isTemplate = false;
        $page->reference = 'route-page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);
        $route->setPage($page);
        $this->manager->flush();

        $this->restContext->resources['route'] = $this->iriConverter->getIriFromResource($route);
        $this->restContext->resources['route_page'] = $this->iriConverter->getIriFromResource($page);
    }

    /**
     * @Given there is a Route :path with a page and a redirect to :redirectPath
     */
    public function thereIsARouteWithPageAndRedirect(string $path, string $redirectPath): void
    {
        $childRoute = new Route();
        $childRoute->setPath($redirectPath)->setName($redirectPath);
        $this->timestampedHelper->persistTimestampedFields($childRoute, true);
        $this->manager->persist($childRoute);

        $page = new Page();
        $page->isTemplate = false;
        $page->reference = 'route-page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $parentRoute = new Route();
        $parentRoute->setPath($path)->setName($path)->setRedirect($childRoute);
        $parentRoute->setPage($page);
        $this->timestampedHelper->persistTimestampedFields($parentRoute, true);
        $this->manager->persist($parentRoute);

        $this->manager->flush();

        $this->restContext->resources['route'] = $this->iriConverter->getIriFromResource($parentRoute);
        $this->restContext->resources['route_page'] = $this->iriConverter->getIriFromResource($page);
        $this->restContext->resources['child_route'] = $this->iriConverter->getIriFromResource($childRoute);
    }

    /**
     * @Given there is a Route :path with a pageData and a redirect to :redirectPath
     */
    public function thereIsARouteWithPageDataAndRedirect(string $path, string $redirectPath): void
    {
        $childRoute = new Route();
        $childRoute->setPath($redirectPath)->setName($redirectPath);
        $this->timestampedHelper->persistTimestampedFields($childRoute, true);
        $this->manager->persist($childRoute);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'template-page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $pageData = new PageData();
        $pageData->setTitle('Test PageData');
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);

        $parentRoute = new Route();
        $parentRoute->setPath($path)->setName($path)->setRedirect($childRoute);
        $parentRoute->setPageData($pageData);
        $this->timestampedHelper->persistTimestampedFields($parentRoute, true);
        $this->manager->persist($parentRoute);

        $this->manager->flush();

        $this->restContext->resources['route'] = $this->iriConverter->getIriFromResource($parentRoute);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);
        $this->restContext->resources['child_route'] = $this->iriConverter->getIriFromResource($childRoute);
    }

    /**
     * @Given there is a Route :path which redirects to :redirectTo
     */
    public function thereIsARouteWithRedirects(string $firstPath, string $redirectTo): void
    {
        $finalRoute = new Route();
        $finalRoute
            ->setPath($redirectTo)
            ->setName($redirectTo);
        $this->timestampedHelper->persistTimestampedFields($finalRoute, true);
        $this->manager->persist($finalRoute);

        $middleRoute = new Route();
        $middleRoute
            ->setPath(bin2hex(random_bytes(10)))
            ->setName(bin2hex(random_bytes(10)))
            ->setRedirect($finalRoute);
        $this->timestampedHelper->persistTimestampedFields($middleRoute, true);
        $this->manager->persist($middleRoute);

        $route = new Route();
        $route
            ->setPath($firstPath)
            ->setName($firstPath)
            ->setRedirect($middleRoute);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        $page = new Page();
        $page->isTemplate = false;
        $page->reference = 'route-page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $finalRoute->setPage($page);
        $this->manager->flush();

        $this->restContext->resources['final_route'] = $this->iriConverter->getIriFromResource($finalRoute);
        $this->restContext->resources['route'] = $this->iriConverter->getIriFromResource($route);
        $this->restContext->resources['middle_route'] = $this->iriConverter->getIriFromResource($middleRoute);
        $this->restContext->resources['route_page'] = $this->iriConverter->getIriFromResource($page);
    }

    /**
     * @Given /^there is a Layout(?: with the reference "([^"]+)")*[ and]*(?: with createdAt "([^"]+)")*(?: with the uiComponent "([^"]+)")*$/
     */
    public function thereIsALayout(string $reference = 'no-reference', ?string $createdAt = null, ?string $uiComponent = null): Layout
    {
        $layout = new Layout();
        $layout->reference = $reference;
        $layout->uiComponent = $uiComponent;
        if (null !== $createdAt) {
            $layout->setCreatedAt(new \DateTimeImmutable($createdAt));
        }
        $this->timestampedHelper->persistTimestampedFields($layout, null === $createdAt);
        $this->manager->persist($layout);
        $this->manager->flush();
        $this->restContext->resources['layout'] = $this->iriConverter->getIriFromResource($layout);

        return $layout;
    }

    /**
     * @Given there is an empty PageData resource
     */
    public function thereIsAnEmptyPageDataResource(): void
    {
        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'test page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $pageData = new PageDataWithComponent();
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);
        $this->manager->flush();
    }

    /**
     * @Given the component position has the dynamic reference :ref
     */
    public function theComponentPositionHasTheDynamicReference(string $ref)
    {
        /** @var ComponentPosition $componentPosition */
        $componentPosition = $this->iriConverter->getResourceFromIri($this->restContext->resources['position_0']);
        $componentPosition->setPageDataProperty($ref);
        $componentPosition->pageDataClass = PageDataWithComponent::class;
        $this->manager->flush();
    }

    /**
     * @Given there is a PageData resource with the route path :route
     */
    public function thereIsAPageDataResourceWithRoutePath(?string $path): void
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'test';
        $componentGroup->location = 'test';
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);

        $componentPosition = new ComponentPosition();
        $componentPosition->pageDataProperty = 'component';
        $componentPosition->pageDataClass = PageDataWithComponent::class;
        $componentPosition->componentGroup = $componentGroup;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);
        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromResource($componentPosition);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'test page';
        $page->addComponentGroup($componentGroup);
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $dummyPublishableComponent = $this->thereIsADummyPublishableComponent();
        $dummyComponent = $this->thereIsADummyComponent();
        $pageData = new PageDataWithComponent();
        $pageData->component = $dummyComponent;
        $pageData->publishableComponent = $dummyPublishableComponent;
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);
        $this->restContext->resources['page_data_manifest'] = '/_/resource_manifest/' . $pageData->getId();
        $this->restContext->resources['page_data_page'] = $this->iriConverter->getIriFromResource($page);
        $this->restContext->resources['page_data_component_group'] = $this->iriConverter->getIriFromResource($componentGroup);

        if ($path) {
            $route = new Route();
            $route
                ->setPath($path)
                ->setName($path)
                ->setPageData($pageData);
            $this->timestampedHelper->persistTimestampedFields($route, true);
            $this->manager->persist($route);
            $this->restContext->resources['page_data_route'] = $this->iriConverter->getIriFromResource($route);
        }

        $this->manager->flush();
    }

    /**
     * @Given there is a PageData resource with a draft component in a pageDataProperty position and the route path :path
     */
    public function thereIsAPageDataWithDraftComponentInPageDataPropertyPosition(string $path): void
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'test';
        $componentGroup->location = 'test';
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);

        $componentPosition = new ComponentPosition();
        $componentPosition->pageDataProperty = 'publishableComponent';
        $componentPosition->pageDataClass = PageDataWithComponent::class;
        $componentPosition->componentGroup = $componentGroup;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'test page';
        $page->addComponentGroup($componentGroup);
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $draftComponent = $this->thereIsADummyPublishableComponent();

        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $draftComponent;
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);

        $route = new Route();
        $route
            ->setPath($path)
            ->setName($path)
            ->setPageData($pageData);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        $this->manager->flush();

        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromResource($componentPosition);
    }

    /**
     * @Given there is a PageData resource with a published component in a pageDataProperty position and the route path :path
     */
    public function thereIsAPageDataWithPublishedComponentInPageDataPropertyPosition(string $path): void
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'test';
        $componentGroup->location = 'test';
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);

        $componentPosition = new ComponentPosition();
        $componentPosition->pageDataProperty = 'publishableComponent';
        $componentPosition->pageDataClass = PageDataWithComponent::class;
        $componentPosition->componentGroup = $componentGroup;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);
        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromResource($componentPosition);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'test page';
        $page->addComponentGroup($componentGroup);
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $publishedComponent = new DummyPublishableComponent();
        $publishedComponent->setPublishedAt(new \DateTime());
        $this->manager->persist($publishedComponent);
        $this->restContext->resources['publishable_component'] = $this->iriConverter->getIriFromResource($publishedComponent);

        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $publishedComponent;
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);

        $route = new Route();
        $route->setPath($path)->setName($path)->setPageData($pageData);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Given there is a pageDataProperty position with a disallowed component type in a restricted group with route :path
     */
    public function thereIsAPageDataPropertyPositionWithDisallowedComponentType(string $path): void
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'test';
        $componentGroup->location = 'test';
        $componentGroup->allowedComponents = ['/component/dummy_components'];
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);
        $this->restContext->resources['component_group'] = $this->iriConverter->getIriFromResource($componentGroup);

        $componentPosition = new ComponentPosition();
        $componentPosition->pageDataProperty = 'publishableComponent';
        $componentPosition->pageDataClass = PageDataWithComponent::class;
        $componentPosition->componentGroup = $componentGroup;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);
        $this->restContext->resources['position_0'] = $this->iriConverter->getIriFromResource($componentPosition);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'test page';
        $page->addComponentGroup($componentGroup);
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $publishableComponent = new DummyPublishableComponent();
        $publishableComponent->setPublishedAt(new \DateTime());
        $this->manager->persist($publishableComponent);

        $pageData = new PageDataWithComponent();
        $pageData->publishableComponent = $publishableComponent;
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);

        $route = new Route();
        $route->setPath($path)->setName($path)->setPageData($pageData);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);
        $this->restContext->resources['page_data_route'] = $this->iriConverter->getIriFromResource($route);

        $this->manager->flush();
    }

    /**
     * @Given there is a pageDataProperty position with an allowed component type in a restricted group with route :path
     */
    public function thereIsAPageDataPropertyPositionWithAllowedComponentType(string $path): void
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'test';
        $componentGroup->location = 'test';
        $componentGroup->allowedComponents = ['/component/dummy_components'];
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);
        $this->restContext->resources['component_group'] = $this->iriConverter->getIriFromResource($componentGroup);

        $componentPosition = new ComponentPosition();
        $componentPosition->pageDataProperty = 'component';
        $componentPosition->pageDataClass = PageDataWithComponent::class;
        $componentPosition->componentGroup = $componentGroup;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);
        $this->restContext->resources['position_0'] = $this->iriConverter->getIriFromResource($componentPosition);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'test page';
        $page->addComponentGroup($componentGroup);
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $dummyComponent = $this->thereIsADummyComponent();

        $pageData = new PageDataWithComponent();
        $pageData->component = $dummyComponent;
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);

        $route = new Route();
        $route->setPath($path)->setName($path)->setPageData($pageData);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);
        $this->restContext->resources['page_data_route'] = $this->iriConverter->getIriFromResource($route);

        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Given there is a PageData resource with the route path :childPath nested within the route :parentPath
     */
    public function thereIsANestedPageDataResource(string $childPath, string $parentPath): void
    {
        $parentPage = new Page();
        $parentPage->isTemplate = true;
        $parentPage->reference = 'parent page';
        $this->timestampedHelper->persistTimestampedFields($parentPage, true);
        $this->manager->persist($parentPage);

        $parentPageData = new PageData();
        $parentPageData->page = $parentPage;
        $this->timestampedHelper->persistTimestampedFields($parentPageData, true);
        $this->manager->persist($parentPageData);

        $parentRoute = new Route();
        $parentRoute->setPath($parentPath)->setName($parentPath)->setPageData($parentPageData);
        $this->timestampedHelper->persistTimestampedFields($parentRoute, true);
        $this->manager->persist($parentRoute);
        $this->restContext->resources['parent_route'] = $this->iriConverter->getIriFromResource($parentRoute);

        $childPage = new Page();
        $childPage->isTemplate = true;
        $childPage->reference = 'child page';
        $this->timestampedHelper->persistTimestampedFields($childPage, true);
        $this->manager->persist($childPage);

        $childPageData = new PageData();
        $childPageData->page = $childPage;
        $childPageData->setParentPageData($parentPageData);
        $this->timestampedHelper->persistTimestampedFields($childPageData, true);
        $this->manager->persist($childPageData);

        $childRoute = new Route();
        $childRoute->setPath($childPath)->setName($childPath)->setPageData($childPageData);
        $this->timestampedHelper->persistTimestampedFields($childRoute, true);
        $this->manager->persist($childRoute);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($childPageData);
        $this->restContext->resources['page_data_manifest'] = '/_/resource_manifest/' . $childPageData->getId();
        $this->restContext->resources['page_data_route'] = $this->iriConverter->getIriFromResource($childRoute);
        $this->restContext->resources['parent_page_data'] = $this->iriConverter->getIriFromResource($parentPageData);

        $this->manager->flush();
    }

    /**
     * @Given there is a PageData resource with the route path :path whose parent page has no route
     */
    public function thereIsAPageDataResourceUnderAnUnroutedParent(string $path): void
    {
        $parentPage = new Page();
        $parentPage->isTemplate = true;
        $parentPage->reference = 'unrouted parent page';
        $this->timestampedHelper->persistTimestampedFields($parentPage, true);
        $this->manager->persist($parentPage);

        $parentPageData = new PageData();
        $parentPageData->page = $parentPage;
        $this->timestampedHelper->persistTimestampedFields($parentPageData, true);
        $this->manager->persist($parentPageData);

        $childPage = new Page();
        $childPage->isTemplate = true;
        $childPage->reference = 'child page';
        $this->timestampedHelper->persistTimestampedFields($childPage, true);
        $this->manager->persist($childPage);

        $childPageData = new PageData();
        $childPageData->page = $childPage;
        $childPageData->setParentPageData($parentPageData);
        $this->timestampedHelper->persistTimestampedFields($childPageData, true);
        $this->manager->persist($childPageData);

        $childRoute = new Route();
        $childRoute->setPath($path)->setName($path)->setPageData($childPageData);
        $this->timestampedHelper->persistTimestampedFields($childRoute, true);
        $this->manager->persist($childRoute);

        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($childPageData);
        $this->restContext->resources['page_data_route'] = $this->iriConverter->getIriFromResource($childRoute);

        $this->manager->flush();
    }

    /**
     * @Given there is a Page resource with the route path :childPath nested within the route :parentPath
     */
    public function thereIsANestedPageResource(string $childPath, string $parentPath): void
    {
        $parentPage = new Page();
        $parentPage->isTemplate = true;
        $parentPage->reference = 'parent page';
        $this->timestampedHelper->persistTimestampedFields($parentPage, true);
        $this->manager->persist($parentPage);

        $parentRoute = new Route();
        $parentRoute->setPath($parentPath)->setName($parentPath)->setPage($parentPage);
        $this->timestampedHelper->persistTimestampedFields($parentRoute, true);
        $this->manager->persist($parentRoute);
        $this->restContext->resources['parent_route'] = $this->iriConverter->getIriFromResource($parentRoute);

        $childPage = new Page();
        $childPage->isTemplate = true;
        $childPage->reference = 'child page';
        $childPage->setParentPage($parentPage);
        $this->timestampedHelper->persistTimestampedFields($childPage, true);
        $this->manager->persist($childPage);

        $childRoute = new Route();
        $childRoute->setPath($childPath)->setName($childPath)->setPage($childPage);
        $this->timestampedHelper->persistTimestampedFields($childRoute, true);
        $this->manager->persist($childRoute);
        $this->manager->flush();

        $this->restContext->resources['parent_page'] = $this->iriConverter->getIriFromResource($parentPage);
        $this->restContext->resources['page'] = $this->iriConverter->getIriFromResource($childPage);
        $this->restContext->resources['page_route'] = $this->iriConverter->getIriFromResource($childRoute);
        $this->restContext->resources['page_manifest'] = '/_/resource_manifest/' . $childPage->getId();
    }

    /**
     * @Given there is a Page resource with the route path :path whose parent page has no route
     */
    public function thereIsAPageResourceUnderAnUnroutedParent(string $path): void
    {
        $parentPage = new Page();
        $parentPage->isTemplate = true;
        $parentPage->reference = 'unrouted parent page';
        $this->timestampedHelper->persistTimestampedFields($parentPage, true);
        $this->manager->persist($parentPage);

        $childPage = new Page();
        $childPage->isTemplate = true;
        $childPage->reference = 'child page';
        $childPage->setParentPage($parentPage);
        $this->timestampedHelper->persistTimestampedFields($childPage, true);
        $this->manager->persist($childPage);

        $childRoute = new Route();
        $childRoute->setPath($path)->setName($path)->setPage($childPage);
        $this->timestampedHelper->persistTimestampedFields($childRoute, true);
        $this->manager->persist($childRoute);

        $this->restContext->resources['page'] = $this->iriConverter->getIriFromResource($childPage);
        $this->restContext->resources['page_route'] = $this->iriConverter->getIriFromResource($childRoute);

        $this->manager->flush();
    }

    /**
     * @Given there is a PageData resource with the route path :childPath nested within the route :parentPath which is nested within the route :grandParentPath
     */
    public function thereIsAThreeLevelNestedPageDataResource(string $childPath, string $parentPath, string $grandParentPath): void
    {
        $grandParentPageData = $this->createRoutedPageData($grandParentPath, 'grandparent page', null);
        $this->restContext->resources['grandparent_route'] = $this->iriConverter->getIriFromResource($grandParentPageData->getRoute());

        $parentPageData = $this->createRoutedPageData($parentPath, 'parent page', $grandParentPageData);
        $this->restContext->resources['parent_route'] = $this->iriConverter->getIriFromResource($parentPageData->getRoute());

        $childPageData = $this->createRoutedPageData($childPath, 'child page', $parentPageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($childPageData);
        $this->restContext->resources['page_data_route'] = $this->iriConverter->getIriFromResource($childPageData->getRoute());

        $this->manager->flush();
    }

    /**
     * @Given there is a PageData resource with the route path :childPath nested within an unrouted page nested within the route :grandParentPath
     */
    public function thereIsARoutedPageDataUnderAnUnroutedIntermediatePage(string $childPath, string $grandParentPath): void
    {
        $grandParentPageData = $this->createRoutedPageData($grandParentPath, 'grandparent page', null);
        $this->restContext->resources['grandparent_route'] = $this->iriConverter->getIriFromResource($grandParentPageData->getRoute());

        $intermediatePage = new Page();
        $intermediatePage->isTemplate = true;
        $intermediatePage->reference = 'unrouted intermediate page';
        $this->timestampedHelper->persistTimestampedFields($intermediatePage, true);
        $this->manager->persist($intermediatePage);

        $intermediatePageData = new PageData();
        $intermediatePageData->page = $intermediatePage;
        $intermediatePageData->setParentPageData($grandParentPageData);
        $this->timestampedHelper->persistTimestampedFields($intermediatePageData, true);
        $this->manager->persist($intermediatePageData);

        $childPageData = $this->createRoutedPageData($childPath, 'child page', $intermediatePageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($childPageData);
        $this->restContext->resources['page_data_route'] = $this->iriConverter->getIriFromResource($childPageData->getRoute());

        $this->manager->flush();
    }

    /**
     * @Given there is a Page with the route :path and an unrouted Page which are each other's parent
     */
    public function thereIsARoutedPageAndAnUnroutedPageWhichAreEachOthersParent(string $path): void
    {
        $routedPage = new Page();
        $routedPage->isTemplate = true;
        $routedPage->reference = 'routed cycle page';
        $this->timestampedHelper->persistTimestampedFields($routedPage, true);
        $this->manager->persist($routedPage);

        $unroutedPage = new Page();
        $unroutedPage->isTemplate = true;
        $unroutedPage->reference = 'unrouted cycle page';
        $this->timestampedHelper->persistTimestampedFields($unroutedPage, true);
        $this->manager->persist($unroutedPage);

        $routedPage->setParentPage($unroutedPage);
        $unroutedPage->setParentPage($routedPage);

        $route = new Route();
        $route->setPath($path)->setName($path)->setPage($routedPage);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);
        $this->restContext->resources['cycle_route'] = $this->iriConverter->getIriFromResource($route);

        $this->manager->flush();
    }

    /**
     * @Given there are two Pages which are each other's parent with the routes :pathOne and :pathTwo
     */
    public function thereAreTwoPagesWhichAreEachOthersParent(string $pathOne, string $pathTwo): void
    {
        $pageOne = new Page();
        $pageOne->isTemplate = true;
        $pageOne->reference = 'cycle page one';
        $this->timestampedHelper->persistTimestampedFields($pageOne, true);
        $this->manager->persist($pageOne);

        $pageTwo = new Page();
        $pageTwo->isTemplate = true;
        $pageTwo->reference = 'cycle page two';
        $this->timestampedHelper->persistTimestampedFields($pageTwo, true);
        $this->manager->persist($pageTwo);

        $pageOne->setParentPage($pageTwo);
        $pageTwo->setParentPage($pageOne);

        foreach ([$pathOne => $pageOne, $pathTwo => $pageTwo] as $path => $page) {
            $route = new Route();
            $route->setPath($path)->setName($path)->setPage($page);
            $this->timestampedHelper->persistTimestampedFields($route, true);
            $this->manager->persist($route);
        }

        $this->manager->flush();
    }

    private function createRoutedPageData(string $path, string $pageReference, ?PageData $parentPageData): PageData
    {
        $page = new Page();
        $page->isTemplate = true;
        $page->reference = $pageReference;
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $pageData = new PageData();
        $pageData->page = $page;
        if (null !== $parentPageData) {
            $pageData->setParentPageData($parentPageData);
        }
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);

        $route = new Route();
        $route->setPath($path)->setName($path)->setPageData($pageData);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        return $pageData;
    }

    /**
     * @When I patch the page with the component group in the request body
     */
    public function iPatchPageWithComponentGroupInBody(): void
    {
        $cgIri = $this->restContext->resources['component_group'];
        $this->restContext->iSendARequestToTheResourceWithBody(
            'PATCH',
            'page',
            null,
            new PyStringNode([\sprintf('{"componentGroups": ["%s"]}', $cgIri)], 0)
        );
    }

    /**
     * @When I patch the page with an embedded component group in the request body
     */
    public function iPatchPageWithEmbeddedComponentGroupInBody(): void
    {
        $cgIri = $this->restContext->resources['component_group'];
        $posIri = $this->restContext->resources['position_0'];
        $body = json_encode([
            'componentGroups' => [
                [
                    '@id' => $cgIri,
                    '@type' => 'ComponentGroup',
                    'componentPositions' => [['@id' => $posIri, 'sortValue' => 0]],
                ],
            ],
        ]);
        $this->restContext->iSendARequestToTheResourceWithBody(
            'PATCH',
            'page',
            null,
            new PyStringNode([$body], 0)
        );
    }

    /**
     * @When I patch the PageData with the property :property and resource :resource
     */
    public function iPatchPageDataWithThePropertyAndResource(string $property, string $resource)
    {
        $iri = $this->restContext->resources[$resource];
        $this->restContext->iSendARequestToTheResourceWithBody(
            'PATCH',
            'page_data',
            null,
            new PyStringNode([\sprintf('{ "%s": "%s" }', $property, $iri)], 0)
        );
    }

    public function abstractThereIsADummyComponentInPageDataAndAPosition(AbstractComponent $dummyComponent, bool $setPageData = true, bool $inPosition = true): void
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'test';
        $componentGroup->location = 'test';
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'test page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $pageData = new PageDataWithComponent();
        $componentPosition = new ComponentPosition();
        if ($setPageData) {
            if ($dummyComponent instanceof DummyComponent) {
                $pageData->component = $dummyComponent;
            } elseif ($dummyComponent instanceof DummyPublishableComponent) {
                $pageData->publishableComponent = $dummyComponent;
            }
        }

        if ($dummyComponent instanceof DummyComponent) {
            $componentPosition->pageDataProperty = 'component';
            $componentPosition->pageDataClass = PageDataWithComponent::class;
        } elseif ($dummyComponent instanceof DummyPublishableComponent) {
            $componentPosition->pageDataProperty = 'publishableComponent';
            $componentPosition->pageDataClass = PageDataWithComponent::class;
        }

        $this->restContext->resources['page_data_component'] = $this->iriConverter->getIriFromResource($dummyComponent);

        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);

        if ($inPosition) {
            $componentPosition->component = $dummyComponent;
        }
        $componentPosition->componentGroup = $componentGroup;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);

        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromResource($componentPosition);

        $this->manager->flush();
    }

    /**
     * @Given there is a DummyComponent in PageData and a Position
     */
    public function thereIsADummyComponentInPageDataAndAPosition()
    {
        $this->abstractThereIsADummyComponentInPageDataAndAPosition($this->thereIsADummyComponent());
    }

    /**
     * @Given there is a DummyComponent in PageData
     */
    public function thereIsADummyComponentInPageData()
    {
        $this->abstractThereIsADummyComponentInPageDataAndAPosition($this->thereIsADummyComponent(), true, false);
    }

    /**
     * @Given there is a DummyComponent in a Position with an empty PageData
     */
    public function thereIsAPageDataAndAPosition()
    {
        $this->abstractThereIsADummyComponentInPageDataAndAPosition($this->thereIsADummyComponent(), false);
    }

    /**
     * @Given there is a component in a route with the path :path
     */
    public function thereIsAComponentInARouteWithPath(string $path): void
    {
        $page = $this->thereIsAPage();

        $route = new Route();
        $route
            ->setPath($path)
            ->setName($path)
            ->setPage($page);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        $componentGroup = $this->thereIsAComponentGroupWithComponents(1);
        $page->addComponentGroup($componentGroup);

        $this->manager->persist($page);
        $this->manager->flush();
    }

    private function thereIsAPageDataPage(Page $page): PageData
    {
        $pageData = new PageData();
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);

        $this->manager->flush();

        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromResource($pageData);

        return $pageData;
    }

    /**
     * @Given there is a component in a PageData route with the path :path
     */
    public function thereIsAComponentInAPageDataRouteWithPath(?string $path): void
    {
        $page = $this->thereIsAPage('page_data_page');

        $pageData = $this->thereIsAPageDataPage($page);

        if ($path) {
            $route = new Route();
            $route
                ->setPath($path)
                ->setName($path)
                ->setPageData($pageData);
            $this->timestampedHelper->persistTimestampedFields($route, true);
            $this->manager->persist($route);
        }

        $componentGroup = $this->thereIsAComponentGroupWithComponents(1, null, 'page_data_cc');
        $page->addComponentGroup($componentGroup);

        $this->manager->persist($page);
        $this->manager->flush();
    }

    /**
     * @Given there is a component in a RestrictedPageData route with the path :path
     */
    public function thereIsAComponentInARestrictedPageDataRouteWithPath(?string $path): void
    {
        $page = $this->thereIsAPage('restricted_page_data_page');

        $pageData = new RestrictedPageData();
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);

        if ($path) {
            $route = new Route();
            $route
                ->setPath($path)
                ->setName($path)
                ->setPageData($pageData);
            $this->timestampedHelper->persistTimestampedFields($route, true);
            $this->manager->persist($route);
        }

        $componentGroup = $this->thereIsAComponentGroupWithComponents(1, null, 'restricted_page_data_cc');
        $page->addComponentGroup($componentGroup);

        $this->manager->persist($page);
        $this->manager->flush();
    }

    /**
     * @Given the resource :resource is in a route with the path :path
     */
    public function theIsAComponentInARouteWithPath(string $resource, string $path): void
    {
        $component = $this->iriConverter->getResourceFromIri($this->restContext->resources[$resource]);
        if (!$component instanceof AbstractComponent) {
            throw new \RuntimeException(\sprintf('The resource named `%s` is not a component', $resource));
        }

        $page = $this->thereIsAPage('page_1');

        $route = new Route();
        $route
            ->setPath($path)
            ->setName($path)
            ->setPage($page);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'test';
        $componentGroup->location = 'test';
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);
        $page->addComponentGroup($componentGroup);

        $componentPosition = new ComponentPosition();
        $componentPosition->component = $component;
        $componentPosition->componentGroup = $componentGroup;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);
        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromResource($componentPosition);

        $this->manager->flush();
    }

    /**
     * @Given The resource :name is removed
     */
    public function theUserUsernameIsRemoved(string $name): void
    {
        $resource = $this->iriConverter->getResourceFromIri($this->restContext->resources[$name]);
        $this->manager->remove($resource);
        $this->manager->flush();
    }

    /**
     * @Given /I have a refresh token(?: which expires at "([^"]*)"|)?$/
     */
    public function iHaveARefreshToken(string $expiresAt = '+10 seconds'): void
    {
        $repo = $this->manager->getRepository(RefreshToken::class);
        $tokens = $repo->findBy([
            'user' => $this->iriConverter->getResourceFromIri($this->restContext->resources['login_user']),
        ]);
        foreach ($tokens as $token) {
            $this->manager->remove($token);
        }

        $refreshToken = new RefreshToken();
        $refreshToken
            ->setUser($this->iriConverter->getResourceFromIri($this->restContext->resources['login_user']))
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt(new \DateTimeImmutable($expiresAt));
        $this->manager->persist($refreshToken);
        $this->manager->flush();
        $this->restContext->resources['refresh_token'] = $refreshToken->getId();
    }

    /**
     * @Given my JWT token has expired
     */
    public function myJwtTokenHasExpired(): void
    {
        $token = $this->jwtEncoder->encode([
            'exp' => (new \DateTime('-1 second'))->getTimestamp(),
            'username' => $this->iriConverter->getResourceFromIri($this->restContext->resources['login_user'])->getUsername(),
        ]);
        $this->minkContext->getSession()->setCookie('api_components', $token);
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', '');
    }

    /**
     * @Given I have an invalid JWT token
     */
    public function iHaveAnInvalidJwtToken(): void
    {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2MDUxMTcxNDMsImV4cCI6MTYwNTEyMDc0Mywicm9sZXMiOlsiUk9MRV9BRE1JTiIsIlJPTEVfVVNFUiJdLCJ1c2VybmFtZSI6ImFkbWluIiwiaWQiOiIxMGE0YjIxNS05NDc5LTRjODAtYmIyOS0yODA3NzFhMDI3ZGUiLCJlbWFpbEFkZHJlc3MiOiJhZG1pbiIsImVtYWlsQWRkcmVzc1ZlcmlmaWVkIjp0cnVlLCJuZXdFbWFpbEFkZHJlc3MiOm51bGx9.K5cZ5dZrQapzUUE6NIA466-EcuqGwRIJ1c0UzKFyz5qKUdVoRaG9NhWG5hIjJVm9ug7VBJypgsLfucwnbVRhseoPGAb0Y88nh9JmI1CI11ImR-BJQm3vT2ff-HqizvHkom13JaLeyXC5WYfB2Ap4b1lG7k_9FTpYsfipcRNkmminUiibce0RMfghRzpiAmd_5kWAI9uqLdHEq1DwJozLO92imYDTec6JtlBJHZX52nvKGJOjas0E9cNQsVChwpRp9EXqEuVI4BtCU428rnrNQr5ExxjsgNPEY4FRHMP72ZthYmQ-37nBfFsskak-e0t26UTdiXS8M8up0BG60lFAzkt09HeqFrGGJzt9ngFPcBPjEp8o7cMBeSSbFf2gU58x8YrdCr0Tq_HOZAOuPiYDz31h9wdPGkySSNq0jhwYK5VIU47VtJbHmWm0gWTdKZ97REXKoc_4D2IhLtwmhNgDH8AUXpy5QIwSlv41Sluw6VktyST-ZmV0_2A9lTzgeN5kRWt4fCRtaSJebd7KdOQgonR69MNvLigOs9YoEWOUMwFuUbetNWbsTs6o8qDBVYDY7Hj1Vrcy3ujCjQJIT-1F7c4GYlAYWMzG8zkumRQ3zZ394ZKRcYaRD8WA2WNYqmc3EpFQFXv9Wjq2j3-5gNJVZFCYoarDcxSXjCb-Ep3LSf8';
        $this->baseRestContext->iAddHeaderEqualTo('Authorization', "Bearer $token");
    }

    /**
     * @Then there should be :count DummyComponent resources
     */
    public function thereShouldBeDummyComponentResources(int $count): void
    {
        $repo = $this->manager->getRepository(DummyComponent::class);
        Assert::assertCount($count, $repo->findAll());
    }

    /**
     * @Then the API docs supportedClass :class should have explicitAllowOnly :expected
     */
    public function theApiDocsSupportedClassShouldHaveExplicitAllowOnly(string $class, string $expected): void
    {
        $json = $this->jsonContext->getJsonAsArray();
        $classes = $json['supportedClass'] ?? $json['hydra:supportedClass'] ?? [];

        $entry = null;
        foreach ($classes as $supportedClass) {
            if (($supportedClass['title'] ?? null) === $class) {
                $entry = $supportedClass;
                break;
            }
        }
        if (null === $entry) {
            throw new \RuntimeException(\sprintf('supportedClass "%s" not found in the API docs.', $class));
        }

        $expectedBool = filter_var($expected, \FILTER_VALIDATE_BOOL);
        $actualBool = true === ($entry['explicitAllowOnly'] ?? false);
        if ($expectedBool !== $actualBool) {
            throw new \RuntimeException(\sprintf('Expected supportedClass "%s" explicitAllowOnly=%s, got %s.', $class, $expectedBool ? 'true' : 'false', $actualBool ? 'true' : 'false'));
        }
    }

    /**
     * @Then the manifest depth :depth root IRI should be :iri
     */
    public function theManifestDepthRootIriShouldBe(int $depth, string $iri): void
    {
        $actual = $this->manifestDepthNode($depth)['iri'] ?? null;
        if ($actual !== $iri) {
            throw new \RuntimeException(\sprintf('Manifest depth %d root IRI is "%s", expected "%s".', $depth, $actual ?? 'null', $iri));
        }
    }

    /**
     * @Then the manifest depth :depth root IRI should be the IRI of the resource :name
     */
    public function theManifestDepthRootIriShouldBeTheIriOfTheResource(int $depth, string $name): void
    {
        $this->theManifestDepthRootIriShouldBe($depth, $this->restContext->resources[$name]);
    }

    /**
     * @Then the manifest depth :depth should have :count resource IRIs
     */
    public function theManifestDepthShouldHaveResourceIris(int $depth, int $count): void
    {
        $iris = $this->flattenManifestNode($this->manifestDepthNode($depth));
        if (\count($iris) !== $count) {
            throw new \RuntimeException(\sprintf('Manifest depth %d has %d IRIs, expected %d: %s', $depth, \count($iris), $count, implode(', ', $iris)));
        }
    }

    /**
     * @Then the manifest depth :depth should contain the IRI :iri
     */
    public function theManifestDepthShouldContainTheIri(int $depth, string $iri): void
    {
        $iris = $this->flattenManifestNode($this->manifestDepthNode($depth));
        if (!\in_array($iri, $iris, true)) {
            throw new \RuntimeException(\sprintf('Manifest depth %d does not contain "%s". Has: %s', $depth, $iri, implode(', ', $iris)));
        }
    }

    /**
     * @Then the manifest depth :depth should contain the IRI of the resource :name
     */
    public function theManifestDepthShouldContainTheIriOfTheResource(int $depth, string $name): void
    {
        $this->theManifestDepthShouldContainTheIri($depth, $this->restContext->resources[$name]);
    }

    /**
     * @Then the manifest depth :depth should not contain the IRI of the resource :name
     */
    public function theManifestDepthShouldNotContainTheIriOfTheResource(int $depth, string $name): void
    {
        $iri = $this->restContext->resources[$name];
        $iris = $this->flattenManifestNode($this->manifestDepthNode($depth));
        if (\in_array($iri, $iris, true)) {
            throw new \RuntimeException(\sprintf('Manifest depth %d unexpectedly contains "%s".', $depth, $iri));
        }
    }

    /**
     * @Then the manifest depth :depth should contain an IRI matching :pattern
     */
    public function theManifestDepthShouldContainAnIriMatching(int $depth, string $pattern): void
    {
        $iris = $this->flattenManifestNode($this->manifestDepthNode($depth));
        foreach ($iris as $iri) {
            if (1 === preg_match($pattern, $iri)) {
                return;
            }
        }
        throw new \RuntimeException(\sprintf('Manifest depth %d has no IRI matching %s. Has: %s', $depth, $pattern, implode(', ', $iris)));
    }

    /**
     * @Then the manifest depth :depth should not contain an IRI matching :pattern
     */
    public function theManifestDepthShouldNotContainAnIriMatching(int $depth, string $pattern): void
    {
        $iris = $this->flattenManifestNode($this->manifestDepthNode($depth));
        foreach ($iris as $iri) {
            if (1 === preg_match($pattern, $iri)) {
                throw new \RuntimeException(\sprintf('Manifest depth %d unexpectedly contains "%s" matching %s.', $depth, $iri, $pattern));
            }
        }
    }

    /**
     * @return array{iri?: string, children?: array}
     */
    private function manifestDepthNode(int $depth): array
    {
        $json = $this->jsonContext->getJsonAsArray();
        $node = $json['resource_iris'][$depth] ?? null;
        if (!\is_array($node)) {
            throw new \RuntimeException(\sprintf('Manifest has no depth %d.', $depth));
        }

        return $node;
    }

    /**
     * @return string[] every iri in the node tree, depth-first
     */
    private function flattenManifestNode(array $node): array
    {
        $iris = [];
        if (isset($node['iri'])) {
            $iris[] = $node['iri'];
        }
        foreach ($node['children'] ?? [] as $child) {
            if (\is_array($child)) {
                $iris = array_merge($iris, $this->flattenManifestNode($child));
            }
        }

        return $iris;
    }

    /**
     * @Then the response resource should be saved as :name
     */
    public function theResponseResourceShouldBeSavedAs($name): void
    {
        $response = $this->jsonContext->getJsonAsArray();
        Assert::assertArrayHasKey('@id', $response);
        $this->restContext->resources[$name] = $response['@id'];
    }

    /**
     * @Then there should be :count Route resources
     */
    public function thereShouldBeRouteResources(int $count): void
    {
        $this->manager->clear();
        $actual = \count($this->manager->getRepository(Route::class)->findAll());
        if ($actual !== $count) {
            throw new ExpectationException(\sprintf('Expected %d Route resources, found %d.', $count, $actual), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then there should be :count ComponentPosition resources
     */
    public function thereShouldBeComponentPositionResources(int $count): void
    {
        $repo = $this->manager->getRepository(ComponentPosition::class);
        Assert::assertCount($count, $repo->findAll());
    }

    /**
     * @Then the resource :name should not exist
     */
    public function theResourceShouldNotExist(string $name): void
    {
        $this->manager->clear();
        try {
            $iri = $this->restContext->resources[$name];
            $this->iriConverter->getResourceFromIri($iri);
            throw new ExpectationException(\sprintf('The resource %s can still be found and has not been removed', $iri), $this->minkContext->getSession()->getDriver());
        } catch (ItemNotFoundException $exception) {
        }
    }

    /**
     * @Then /^the refresh token should (not )?be expired$/
     */
    public function theRefreshTokenShouldBeExpired(string $not = ''): void
    {
        $this->manager->clear();
        $repo = $this->manager->getRepository(RefreshToken::class);
        $token = $repo->findOneBy([
            'user' => $this->iriConverter->getResourceFromIri($this->restContext->resources['login_user']),
        ]);
        $expectExpired = '' === $not;
        if ($token->isExpired() !== $expectExpired) {
            throw new ExpectationException(\sprintf('The token with ID %s is %s', $this->restContext->resources['refresh_token'], $expectExpired ? 'not expired' : 'expired'), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @When /^I run the refresh tokens expire command for "([^"]*)"(?: using the field "([^"]*)")?$/
     */
    public function iRunTheRefreshTokensExpireCommandFor(string $value, string $field = ''): void
    {
        $input = ['username' => $value];
        if ('' !== $field) {
            $input['--field'] = $field;
        }
        $application = new Application($this->kernel);
        $tester = new CommandTester($application->find('silverback:api-components:refresh-tokens:expire'));
        $this->commandException = null;
        try {
            $tester->execute($input);
        } catch (\Throwable $exception) {
            $this->commandException = $exception;
        }
    }

    /**
     * @Then the command should have succeeded
     */
    public function theCommandShouldHaveSucceeded(): void
    {
        if (null !== $this->commandException) {
            throw new ExpectationException(\sprintf('The command failed: %s', $this->commandException->getMessage()), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then the command should have failed with a message containing :text
     */
    public function theCommandShouldHaveFailedWithAMessageContaining(string $text): void
    {
        if (null === $this->commandException) {
            throw new ExpectationException('The command succeeded', $this->minkContext->getSession()->getDriver());
        }
        if (!str_contains($this->commandException->getMessage(), $text)) {
            throw new ExpectationException(\sprintf('The command failed with "%s", which does not contain "%s"', $this->commandException->getMessage(), $text), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then /^all the refresh tokens should be expired$/
     */
    public function allTheRefreshTokensShouldBeExpired(): void
    {
        $this->manager->clear();
        $repo = $this->manager->getRepository(RefreshToken::class);
        $tokens = $repo->findBy([
            'user' => $this->iriConverter->getResourceFromIri($this->restContext->resources['login_user']),
        ]);
        foreach ($tokens as $token) {
            if (!$token->isExpired()) {
                throw new ExpectationException(\sprintf('The token with ID %s is not expired', $this->restContext->resources['refresh_token']), $this->minkContext->getSession()->getDriver());
            }
        }
    }

    /**
     * @Then the resource :name should exist
     */
    public function theResourceShouldExist(string $name): void
    {
        $this->manager->clear();
        try {
            $iri = $this->restContext->resources[$name];
            $this->iriConverter->getResourceFromIri($iri);
        } catch (ItemNotFoundException $exception) {
            throw new ExpectationException(\sprintf('The resource %s cannot be found anymore', $iri), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then the password should be :password for username :username
     */
    public function thePasswordShouldBeEqualTo(string $password, string $username): void
    {
        /** @var UserRepositoryInterface $repository */
        $repository = $this->manager->getRepository(User::class);
        /** @var AbstractUser $user */
        $user = $repository->loadUserByIdentifier($username);
        Assert::assertTrue($this->passwordHasher->isPasswordValid($user, $password));
    }

    /**
     * @Then the new email address should be :emailAddress for username :username
     */
    public function theEmailAddressShouldBe(string $emailAddress, string $username): void
    {
        $this->manager->clear();
        $repository = $this->manager->getRepository(User::class);
        /** @var AbstractUser $user */
        $user = $repository->findOneBy(
            [
                'username' => $username,
            ]
        );
        Assert::assertEquals($emailAddress, $user->getEmailAddress());
        Assert::assertNull($user->getNewEmailAddress());
    }

    /**
     * @Then there should be no user with the username :username
     */
    public function thereShouldBeNoUserWithTheUsername(string $username): void
    {
        $this->manager->clear();
        $count = \count($this->manager->getRepository(User::class)->findBy(['username' => $username]));
        if (0 !== $count) {
            throw new ExpectationException(\sprintf('Expected no user with the username "%s" but found %d', $username, $count), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then /^the (password reset|email verification|new email confirmation) token for the user "([^"]+)" should still be "([^"]+)"$/
     */
    public function theUserTokenShouldStillBe(string $tokenName, string $username, string $token): void
    {
        $this->manager->clear();
        /** @var AbstractUser|null $user */
        $user = $this->manager->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user) {
            throw new \RuntimeException(\sprintf('The user `%s` does not exist', $username));
        }
        $storedToken = match ($tokenName) {
            'password reset' => $user->getNewPasswordConfirmationToken(),
            'email verification' => $user->getEmailAddressVerifyToken(),
            'new email confirmation' => $user->getNewEmailConfirmationToken(),
        };
        if (null === $storedToken || !$this->passwordHasherFactory->getPasswordHasher($user)->verify($storedToken, $token)) {
            throw new \RuntimeException(\sprintf('The %s token for the user `%s` is no longer `%s`', $tokenName, $username, $token));
        }
    }

    /**
     * @Then the user :username should have no pending new email address
     */
    public function theUserShouldHaveNoPendingNewEmailAddress(string $username): void
    {
        $this->manager->clear();
        /** @var AbstractUser|null $user */
        $user = $this->manager->getRepository(User::class)->findOneBy(['username' => $username]);
        if (!$user) {
            throw new \RuntimeException(\sprintf('The user `%s` does not exist', $username));
        }
        if (null !== $user->getNewEmailAddress() || null !== $user->getNewEmailConfirmationToken()) {
            throw new \RuntimeException(\sprintf('The user `%s` still has the pending new email address `%s`', $username, $user->getNewEmailAddress()));
        }
    }

    /**
     * @Then the user :username should have a verified email address
     */
    public function theUserShouldHaveAVerifiedEmailAddress(string $username): void
    {
        $this->manager->clear();
        $repository = $this->manager->getRepository(User::class);
        /** @var AbstractUser $user */
        $user = $repository->findOneBy(
            [
                'username' => $username,
            ]
        );
        Assert::assertTrue($user->isEmailAddressVerified());
    }

    /**
     * @Then the user :username should have an unverified email address
     */
    public function theUserShouldHaveAnUnverifiedEmailAddress(string $username): void
    {
        $this->manager->clear();
        $repository = $this->manager->getRepository(User::class);
        /** @var AbstractUser $user */
        $user = $repository->findOneBy(
            [
                'username' => $username,
            ]
        );
        Assert::assertFalse($user->isEmailAddressVerified());
    }

    /**
     * @Given the Route :path goes live at :dateTime
     */
    public function theRouteGoesLiveAt(string $path, string $dateTime): void
    {
        $this->setRouteLiveAt($path, new \DateTimeImmutable($dateTime));
    }

    /**
     * @Given the Route :path goes live in :seconds seconds
     */
    public function theRouteGoesLiveInSeconds(string $path, int $seconds): void
    {
        $this->setRouteLiveAt($path, (new \DateTimeImmutable())->modify(\sprintf('+%d seconds', $seconds)));
    }

    /**
     * @Given the Route :path has no go-live date
     */
    public function theRouteHasNoGoLiveDate(string $path): void
    {
        $this->setRouteLiveAt($path, null);
    }

    /**
     * @Then the Route :path should be live
     */
    public function theRouteShouldBeLive(string $path): void
    {
        Assert::assertTrue($this->isRouteLive($path), \sprintf('The route "%s" is not live.', $path));
    }

    /**
     * @Then the Route :path should not be live
     */
    public function theRouteShouldNotBeLive(string $path): void
    {
        Assert::assertFalse($this->isRouteLive($path), \sprintf('The route "%s" is live.', $path));
    }

    private function setRouteLiveAt(string $path, ?\DateTimeImmutable $liveAt): void
    {
        $route = $this->manager->getRepository(Route::class)->findOneBy(['path' => $path]);
        Assert::assertNotNull($route, \sprintf('No route found with the path "%s".', $path));
        $route->setLiveAt($liveAt);
        $this->manager->flush();
        $this->manager->clear();
    }

    private function isRouteLive(string $path): bool
    {
        $this->manager->clear();
        $route = $this->manager->getRepository(Route::class)->findOneBy(['path' => $path]);
        Assert::assertNotNull($route, \sprintf('No route found with the path "%s".', $path));

        return $this->routeLiveResolver->isLive($route);
    }

    /**
     * @Then the Route :oldPath should redirect to :newPath
     */
    public function theRouteShouldRedirectTo(string $oldPath, string $newPath): void
    {
        $this->manager->clear();
        $repository = $this->manager->getRepository(Route::class);

        $route = $repository->findOneBy(['path' => $oldPath]);
        $redirectPath = $route?->getRedirect()?->getPath();
        if ($newPath !== $redirectPath) {
            throw new ExpectationException(\sprintf('Expected the Route "%s" to redirect to "%s", but it redirects to %s.', $oldPath, $newPath, null === $redirectPath ? 'nothing' : \sprintf('"%s"', $redirectPath)), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then the Route :path should not redirect
     */
    public function theRouteShouldNotRedirect(string $path): void
    {
        $this->manager->clear();
        $repository = $this->manager->getRepository(Route::class);
        $route = $repository->findOneBy(['path' => $path]);
        Assert::assertNotNull($route, \sprintf('Expected route "%s" to exist.', $path));
        Assert::assertNull($route->getRedirect(), \sprintf('Expected route "%s" to have no redirect, but it does.', $path));
    }

    /**
     * @Then /^(\d+) refresh token(?:s)? should exist$/
     */
    public function aRefreshTokenShouldHaveBeenGenerated(int $count): void
    {
        $this->manager->clear();
        $repository = $this->manager->getRepository(RefreshToken::class);
        $allTokens = $repository->findAll();
        Assert::assertCount($count, $allTokens);
        $nonExpiredCount = 0;
        foreach ($allTokens as $token) {
            if (!$token->isExpired()) {
                ++$nonExpiredCount;
            }
        }
        Assert::assertLessThanOrEqual(1, $nonExpiredCount, \sprintf('There should only be 1 token that is not expired. There are %d', $nonExpiredCount));
    }

    /**
     * @Given there is a published DummyPublishableComponent in :count component positions
     */
    public function thereIsAPublishedDummyPublishableComponentInPositions(int $count): void
    {
        $component = new DummyPublishableComponent();
        $component->setPublishedAt(new \DateTime());
        $this->manager->persist($component);
        $this->restContext->resources['publishable_component'] = $this->iriConverter->getIriFromResource($component);

        for ($i = 0; $i < $count; ++$i) {
            $componentGroup = new ComponentGroup();
            $componentGroup->reference = 'test_group_' . $i;
            $componentGroup->location = 'test_group_' . $i;
            $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
            $this->manager->persist($componentGroup);

            $position = new ComponentPosition();
            $position->sortValue = 0;
            $position->component = $component;
            $position->componentGroup = $componentGroup;
            $this->timestampedHelper->persistTimestampedFields($position, true);
            $this->manager->persist($position);
        }

        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @BeforeScenario
     *
     * @AfterScenario
     */
    public function clearOrphanedResourcesReport(): void
    {
        $this->orphanedResourceReportStore->clear();
    }

    /**
     * @Given an orphaned resources report has been stored
     */
    public function anOrphanedResourcesReportHasBeenStored(): void
    {
        $this->orphanedResourceReportStore->save(new OrphanedResourceReport(new \DateTimeImmutable()));
    }

    /**
     * @Then no orphaned resources report should have been stored
     */
    public function noOrphanedResourcesReportShouldHaveBeenStored(): void
    {
        if (null !== $this->orphanedResourceReportStore->fetch()) {
            throw new \RuntimeException('An orphaned resources report was stored');
        }
    }

    /**
     * @Given there is an orphaned ComponentGroup
     */
    public function thereIsAnOrphanedComponentGroup(): void
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = 'orphaned-group';
        $componentGroup->location = 'orphaned-group';
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);
        $this->manager->flush();
        $this->restContext->resources['orphaned_component_group'] = $this->iriConverter->getIriFromResource($componentGroup);
    }

    /**
     * @Given the Page :name has a ComponentPosition with neither a component nor a page data property
     */
    public function thePageHasAnEmptyComponentPosition(string $name): void
    {
        $position = new ComponentPosition();
        $position->componentGroup = $this->createPageComponentGroup($name, 'empty-position-group');
        $position->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($position, true);
        $this->manager->persist($position);
        $this->manager->flush();
        $this->restContext->resources['empty_position'] = $this->iriConverter->getIriFromResource($position);
    }

    /**
     * @Given the Page :name holds the resource :resource
     */
    public function thePageHoldsTheResource(string $name, string $resource): void
    {
        /** @var AbstractComponent $component */
        $component = $this->iriConverter->getResourceFromIri($this->restContext->resources[$resource]);
        $position = new ComponentPosition();
        $position->componentGroup = $this->createPageComponentGroup($name, 'held-resource-group');
        $position->component = $component;
        $position->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($position, true);
        $this->manager->persist($position);
        $this->manager->flush();
    }

    /**
     * @Given the resource :name has been removed from the database
     */
    public function theResourceHasBeenRemovedFromTheDatabase(string $name): void
    {
        $this->manager->clear();
        $resource = $this->iriConverter->getResourceFromIri($this->restContext->resources[$name]);
        $this->manager->remove($resource);
        $this->manager->flush();
        $this->manager->clear();
    }

    /**
     * @Given /^there is a DummyComponent held only by a page data property typed as its parent class(?: with the route path "([^"]*)")?$/
     */
    public function thereIsADummyComponentHeldByAParentTypedPageDataProperty(string $path = ''): void
    {
        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'parent typed page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $component = $this->thereIsADummyComponent();

        $pageData = new PageDataWithParentTypedComponent();
        $pageData->component = $component;
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);

        if ('' !== $path) {
            $route = new Route();
            $route->setPath($path)->setName($path)->setPageData($pageData);
            $this->timestampedHelper->persistTimestampedFields($route, true);
            $this->manager->persist($route);
        }

        $this->manager->flush();
        $this->restContext->resources['parent_typed_page_data'] = $this->iriConverter->getIriFromResource($pageData);
        $this->manager->clear();
    }

    /**
     * @When I run the clean orphaned command
     */
    public function iRunTheCleanOrphanedCommand(): void
    {
        $application = new Application($this->kernel);
        $tester = new CommandTester($application->find('silverback:api-components:clean-orphaned'));
        $this->commandException = null;
        try {
            $tester->execute([]);
        } catch (\Throwable $exception) {
            $this->commandException = $exception;
        }
        $this->manager->clear();
    }

    /**
     * @Then the page data :name should still hold the resource :resource
     */
    public function thePageDataShouldStillHoldTheResource(string $name, string $resource): void
    {
        $this->manager->clear();
        $pageData = $this->iriConverter->getResourceFromIri($this->restContext->resources[$name]);
        $component = $pageData->component ?? null;
        if (!$component instanceof AbstractComponent || $this->iriConverter->getIriFromResource($component) !== $this->restContext->resources[$resource]) {
            throw new \RuntimeException(\sprintf('The page data %s does not hold the resource %s', $this->restContext->resources[$name], $this->restContext->resources[$resource]));
        }
    }

    private function createPageComponentGroup(string $pageName, string $reference): ComponentGroup
    {
        /** @var Page $page */
        $page = $this->iriConverter->getResourceFromIri($this->restContext->resources[$pageName]);
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = $reference;
        $componentGroup->location = $reference;
        $this->timestampedHelper->persistTimestampedFields($componentGroup, true);
        $this->manager->persist($componentGroup);
        $page->addComponentGroup($componentGroup);

        return $componentGroup;
    }
}
