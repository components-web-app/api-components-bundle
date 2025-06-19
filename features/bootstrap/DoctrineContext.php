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

use ApiPlatform\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Behatch\Context\RestContext as BehatchRestContext;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;
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
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyCustomTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestampedWithSerializationGroups;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RefreshToken;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedPageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\NestedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestRepeatedType;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;
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
    private SchemaTool $schemaTool;
    private UserPasswordHasherInterface $passwordHasher;
    private array $classes;
    private JWTEncoderInterface $jwtEncoder;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct(ManagerRegistry $doctrine, JWTTokenManagerInterface $jwtManager, IriConverterInterface $iriConverter, TimestampedDataPersister $timestampedHelper, UserPasswordHasherInterface $passwordHasher, JWTEncoderInterface $jwtEncoder)
    {
        $this->doctrine = $doctrine;
        $this->jwtManager = $jwtManager;
        $this->iriConverter = $iriConverter;
        $this->timestampedHelper = $timestampedHelper;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
        $this->passwordHasher = $passwordHasher;
        $this->jwtEncoder = $jwtEncoder;
    }

    /**
     * @BeforeSuite
     */
    public static function clearAppCache(): void
    {
        exec('php tests/Functional/app/bin/console cache:clear --env=test --no-warmup');
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
     * @Given(removed as was not used in features, only internal) there is a DummyPublishableComponent
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
     * @Given /^there is a ComponentGroup with (\d+) components(?:| and the ID "([^"]+)")$/
     */
    public function thereIsAComponentGroupWithComponents(int $count, ?string $id = null, string $collectionReference = 'collection'): ComponentGroup
    {
        $componentGroup = new ComponentGroup();
        $componentGroup->reference = $collectionReference;
        $componentGroup->location = $collectionReference;
        $componentGroup->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->manager->persist($componentGroup);
        if ($id) {
            $reflection = new \ReflectionClass($componentGroup);
            $reflectionProp = $reflection->getProperty('id');
            $reflectionProp->setAccessible(true);
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
            $this->restContext->resources['component_' . $x] = $this->iriConverter->getIriFromResource($component);
            $this->restContext->resources['position_' . $x] = $this->iriConverter->getIriFromResource($position);
        }
        $this->manager->flush();

        $this->restContext->resources['component_group'] = $this->iriConverter->getIriFromResource($componentGroup);

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
    public function thereIsALayout(string $reference = 'no-reference', ?string $createdAt = null, ?string $uiComponent = null): void
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
        $componentPosition->componentGroup = $componentGroup;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);
        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromResource($componentPosition);

        $page = new Page();
        $page->isTemplate = true;
        $page->reference = 'test page';
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
     * @When I patch the PageData with the property :property and resource :resource
     */
    public function iPatchPageDataWithThePropertyAndResource(string $property, string $resource)
    {
        $iri = $this->restContext->resources[$resource];
        $this->restContext->iSendARequestToTheResourceWithBody(
            'PUT',
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
        } elseif ($dummyComponent instanceof DummyPublishableComponent) {
            $componentPosition->pageDataProperty = 'publishableComponent';
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
            ->setCreatedAt(new \DateTime())
            ->setExpiresAt(new \DateTime($expiresAt));
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
     * @Then the refresh token should be expired
     */
    public function theRefreshTokenShouldBeExpired(): void
    {
        $this->manager->clear();
        $repo = $this->manager->getRepository(RefreshToken::class);
        $token = $repo->findOneBy([
            'user' => $this->iriConverter->getResourceFromIri($this->restContext->resources['login_user']),
        ]);
        if (!$token->isExpired()) {
            throw new ExpectationException(\sprintf('The token with ID %s is not expired', $this->restContext->resources['refresh_token']), $this->minkContext->getSession()->getDriver());
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
     * @Then the Route :oldPath should redirect to :newPath
     */
    public function theRouteShouldRedirectTo(string $oldPath, string $newPath): void
    {
        $this->manager->clear();
        $repository = $this->manager->getRepository(Route::class);

        /** @var Route $route */
        $route = $repository->findOneBy(
            [
                'path' => $oldPath,
            ]
        );
        Assert::assertEquals($newPath, $route->getRedirect()->getPath());
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
}
