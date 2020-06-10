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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentCollection;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserLoginType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyCustomTimestamped;
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
    private JWTEncoderInterface $jwtEncoder;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct(ManagerRegistry $doctrine, JWTTokenManagerInterface $jwtManager, IriConverterInterface $iriConverter, TimestampedDataPersister $timestampedHelper, UserPasswordEncoderInterface $passwordEncoder, JWTEncoderInterface $jwtEncoder)
    {
        $this->doctrine = $doctrine;
        $this->jwtManager = $jwtManager;
        $this->iriConverter = $iriConverter;
        $this->timestampedHelper = $timestampedHelper;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
        $this->passwordEncoder = $passwordEncoder;
        $this->jwtEncoder = $jwtEncoder;
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
            ->setUsername('user@example.com')
            ->setPassword($this->passwordEncoder->encodePassword($user, 'password'))
            ->setEnabled(true)
            ->setEmailAddressVerified(true);
        $this->timestampedHelper->persistTimestampedFields($user, true);
        $this->manager->persist($user);
        $this->manager->flush();

        $token = $this->jwtManager->create($user);
        if ($useAuthHeader) {
            $this->baseRestContext->iAddHeaderEqualTo('Authorization', "Bearer $token");
        } else {
            $this->minkContext->getSession()->setCookie('api_component', $token);
        }
        $this->restContext->resources['login_user'] = $this->iriConverter->getIriFromItem($user);
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
        $this->restContext->resources[$type . '_form'] = $this->iriConverter->getIriFromItem($form);
    }

    /**
     * @Given /^there is a user with the username "([^"]*)" password "([^"]*)" and role "([^"]*)"(?: and the email address "([^"]*)"|)$/i
     */
    public function thereIsAUserWithUsernamePasswordAndRole(string $username, string $password, string $role, string $emailAddress = 'test.user@example.com'): void
    {
        $user = new User();
        $user
            ->setUsername($username)
            ->setEmailAddress($emailAddress)
            ->setPassword($this->passwordEncoder->encodePassword($user, $password))
            ->setRoles([$role])
            ->setEnabled(true)
            ->setEmailAddressVerified(true);
        $this->timestampedHelper->persistTimestampedFields($user, true);
        $this->manager->persist($user);
        $this->manager->flush();
        $this->restContext->resources['user'] = $this->iriConverter->getIriFromItem($user);
    }

    /**
     * @Given the user has the newPasswordConfirmationToken :token requested at :dateTime
     */
    public function theUserHasTheNewPasswordConfirmationToken(string $token, string $dateTime): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getItemFromIri($this->restContext->resources['user']);
        $user->setNewPasswordConfirmationToken($this->passwordEncoder->encodePassword($user, $token))->setPasswordRequestedAt(new \DateTime($dateTime));
        $this->manager->flush();
    }

    /**
     * @Given the user is disabled
     */
    public function theUserIsDisabled(): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getItemFromIri($this->restContext->resources['user']);
        $user->setEnabled(false);
        $this->manager->flush();
    }

    /**
     * @Given /^the user email is not verified(?: with the token "([^"]+)"|)$/
     */
    public function theUserEmailIsNotVerified(?string $verificationToken = null): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getItemFromIri($this->restContext->resources['user']);
        $user->setEmailAddressVerified(false);
        if ($verificationToken) {
            $user->setEmailAddressVerifyToken($this->passwordEncoder->encodePassword($user, $verificationToken));
        }
        $this->manager->flush();
    }

    /**
     * @Given the user has a new email address :emailAddress and confirmation token :token
     */
    public function theUserHasANewEmailAddress(string $emailAddress, string $verificationToken): void
    {
        /** @var User $user */
        $user = $this->iriConverter->getItemFromIri($this->restContext->resources['user']);
        $user->setNewEmailAddress($emailAddress)->setNewEmailConfirmationToken($this->passwordEncoder->encodePassword($user, $verificationToken));
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
        $this->restContext->resources['dummy_component'] = $this->iriConverter->getIriFromItem($component);

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
        $this->restContext->resources['restricted_component'] = $this->iriConverter->getIriFromItem($component);
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
        $this->restContext->resources['dummy_custom_timestamped'] = $this->iriConverter->getIriFromItem($component);
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
        $this->restContext->resources['dummy_custom_timestamped'] = $this->iriConverter->getIriFromItem($component);
    }

    /**
     * @Given /^there is a ComponentCollection with (\d+) components(?:| and the ID "([^"]+)")$/
     */
    public function thereIsAComponentCollectionWithComponents(int $count, ?string $id = null, string $collectionReference = 'collection'): ComponentCollection
    {
        $componentCollection = new ComponentCollection();
        $componentCollection->reference = $collectionReference;
        $componentCollection->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->manager->persist($componentCollection);
        if ($id) {
            $reflection = new \ReflectionClass($componentCollection);
            $reflectionProp = $reflection->getProperty('id');
            $reflectionProp->setAccessible(true);
            $reflectionProp->setValue($componentCollection, Uuid::fromString($id));
        }

        for ($x = 0; $x < $count; ++$x) {
            $component = new DummyComponent();
            $this->manager->persist($component);
            $position = new ComponentPosition();
            $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
            $position->sortValue = $x;
            $position->componentCollection = $componentCollection;
            $position->component = $component;
            $this->manager->persist($position);
            $this->restContext->resources['component_' . $x] = $this->iriConverter->getIriFromItem($component);
            $this->restContext->resources['position_' . $x] = $this->iriConverter->getIriFromItem($position);
        }
        $this->manager->flush();

        $this->restContext->resources['component_collection'] = $this->iriConverter->getIriFromItem($componentCollection);

        return $componentCollection;
    }

    /**
     * @Given the ComponentCollection has the allowedComponent :allowedComponent
     */
    public function theComponentCollectionHasTheAllowedComponents(string $allowedComponent): void
    {
        /** @var ComponentCollection $collection */
        $collection = $this->iriConverter->getItemFromIri($this->restContext->resources['component_collection']);
        if ('' !== $allowedComponent) {
            $collection->allowedComponents = new ArrayCollection([$allowedComponent]);
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
        $page->reference = $reference;
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);
        $this->manager->flush();
        $this->restContext->resources['page'] = $this->iriConverter->getIriFromItem($page);

        return $page;
    }

    /**
     * @Given /^there (?:is|are) (\d+) Route(?:s)?$/
     */
    public function thereAreRoutes(int $count): void
    {
        for ($x = 0; $x < $count; ++$x) {
            $route = new Route();
            $route
                ->setPath(sprintf('/route-%s', $x))
                ->setName(sprintf('/route-%s', $x));
            $this->timestampedHelper->persistTimestampedFields($route, true);
            $this->manager->persist($route);
            $this->restContext->resources['route_' . $x] = $this->iriConverter->getIriFromItem($route);
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
        $page->reference = 'route-page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);
        $route->setPage($page);
        $this->manager->flush();

        $this->restContext->resources['route'] = $this->iriConverter->getIriFromItem($route);
        $this->restContext->resources['route_page'] = $this->iriConverter->getIriFromItem($page);
    }

    /**
     * @Given there is a Route :path with redirects to :redirectTo
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
        $page->reference = 'route-page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $finalRoute->setPage($page);
        $this->manager->flush();

        $this->restContext->resources['route'] = $this->iriConverter->getIriFromItem($route);
        $this->restContext->resources['route_page'] = $this->iriConverter->getIriFromItem($page);
    }

    /**
     * @Given /^there is a Layout(?: with the reference "([^"]*)"|)$/
     */
    public function thereIsALayout(string $reference = 'no-reference'): void
    {
        $layout = new Layout();
        $layout->reference = $reference;
        $this->timestampedHelper->persistTimestampedFields($layout, true);
        $this->manager->persist($layout);
        $this->manager->flush();
        $this->restContext->resources['layout'] = $this->iriConverter->getIriFromItem($layout);
    }

    /**
     * @Given there is a PageData resource with the route path :route
     */
    public function thereIsAPageDataResourceWithRoutePath(?string $path): void
    {
        $componentCollection = new ComponentCollection();
        $componentCollection->reference = 'test';
        $this->timestampedHelper->persistTimestampedFields($componentCollection, true);
        $this->manager->persist($componentCollection);

        $componentPosition = new ComponentPosition();
        $componentPosition->pageDataProperty = 'component';
        $componentPosition->componentCollection = $componentCollection;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);
        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromItem($componentPosition);

        $page = new Page();
        $page->reference = 'test page';
        $this->timestampedHelper->persistTimestampedFields($page, true);
        $this->manager->persist($page);

        $dummyComponent = $this->thereIsADummyComponent();
        $pageData = new PageDataWithComponent();
        $pageData->component = $dummyComponent;
        $pageData->page = $page;
        $this->timestampedHelper->persistTimestampedFields($pageData, true);
        $this->manager->persist($pageData);
        $this->restContext->resources['page_data'] = $this->iriConverter->getIriFromItem($pageData);

        if ($path) {
            $route = new Route();
            $route
                ->setPath($path)
                ->setName($path)
                ->setPageData($pageData);
            $this->timestampedHelper->persistTimestampedFields($route, true);
            $this->manager->persist($route);
        }

        $this->manager->flush();
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

        $componentCollection = $this->thereIsAComponentCollectionWithComponents(1);
        $page->addComponentCollection($componentCollection);

        $this->manager->persist($page);
        $this->manager->flush();
    }

    /**
     * @Given there is a component in a PageData route with the path :path
     */
    public function thereIsAComponentInAPageDataRouteWithPath(?string $path): void
    {
        $page = $this->thereIsAPage('page_data_page');

        $pageData = new PageData();
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

        $componentCollection = $this->thereIsAComponentCollectionWithComponents(1, null, 'page_data_cc');
        $page->addComponentCollection($componentCollection);

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

        $componentCollection = $this->thereIsAComponentCollectionWithComponents(1, null, 'restricted_page_data_cc');
        $page->addComponentCollection($componentCollection);

        $this->manager->persist($page);
        $this->manager->flush();
    }

    /**
     * @Given the resource :resource is in a route with the path :path
     */
    public function theIsAComponentInARouteWithPath(string $resource, string $path): void
    {
        $component = $this->iriConverter->getItemFromIri($this->restContext->resources[$resource]);

        $page = $this->thereIsAPage('page_1');

        $route = new Route();
        $route
            ->setPath($path)
            ->setName($path)
            ->setPage($page);
        $this->timestampedHelper->persistTimestampedFields($route, true);
        $this->manager->persist($route);

        $componentCollection = new ComponentCollection();
        $componentCollection->reference = 'test';
        $this->timestampedHelper->persistTimestampedFields($componentCollection, true);
        $this->manager->persist($componentCollection);
        $page->addComponentCollection($componentCollection);

        $componentPosition = new ComponentPosition();
        $componentPosition->component = $component;
        $componentPosition->componentCollection = $componentCollection;
        $componentPosition->sortValue = 0;
        $this->timestampedHelper->persistTimestampedFields($componentPosition, true);
        $this->manager->persist($componentPosition);
        $this->restContext->resources['component_position'] = $this->iriConverter->getIriFromItem($componentPosition);

        $this->manager->flush();
    }

    /**
     * @Given /I have a refresh token(?: which expires at "([^"]*)"|)?$/
     */
    public function iHaveARefreshToken(string $expiresAt = '+10 seconds'): void
    {
        $repo = $this->manager->getRepository(RefreshToken::class);
        $tokens = $repo->findBy([
            'user' => $this->iriConverter->getItemFromIri($this->restContext->resources['login_user']),
        ]);
        foreach ($tokens as $token) {
            $this->manager->remove($token);
        }

        $refreshToken = new RefreshToken();
        $refreshToken
            ->setUser($this->iriConverter->getItemFromIri($this->restContext->resources['login_user']))
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
            'username' => $this->iriConverter->getItemFromIri($this->restContext->resources['login_user'])->getUsername(),
        ]);
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
            $this->iriConverter->getItemFromIri($iri);
            throw new ExpectationException(sprintf('The resource %s can still be found and has not been removed', $iri), $this->minkContext->getSession()->getDriver());
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
            'user' => $this->iriConverter->getItemFromIri($this->restContext->resources['login_user']),
        ]);
        if (!$token->isExpired()) {
            throw new ExpectationException(sprintf('The token with ID %s is not expired', $this->restContext->resources['refresh_token']), $this->minkContext->getSession()->getDriver());
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
            'user' => $this->iriConverter->getItemFromIri($this->restContext->resources['login_user']),
        ]);
        foreach ($tokens as $token) {
            if (!$token->isExpired()) {
                throw new ExpectationException(sprintf('The token with ID %s is not expired', $this->restContext->resources['refresh_token']), $this->minkContext->getSession()->getDriver());
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
            $this->iriConverter->getItemFromIri($iri);
        } catch (ItemNotFoundException $exception) {
            throw new ExpectationException(sprintf('The resource %s cannot be found anymore', $iri), $this->minkContext->getSession()->getDriver());
        }
    }

    /**
     * @Then the password should be :password for username :username
     */
    public function thePasswordShouldBeEqualTo(string $password, string $username): void
    {
        $repository = $this->manager->getRepository(User::class);
        /** @var AbstractUser $user */
        $user = $repository->findOneBy(
            [
                'username' => $username,
            ]
        );
        Assert::assertTrue($this->passwordEncoder->isPasswordValid($user, $password));
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
        Assert::assertCount($count, $repository->findAll());
    }
}
