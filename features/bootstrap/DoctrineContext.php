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

use ApiPlatform\Core\Api\IriConverterInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Silverback\ApiComponentBundle\Entity\Component\Form;
use Silverback\ApiComponentBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Form\TestType;

final class DoctrineContext implements Context
{
    private ManagerRegistry $doctrine;
    private RestContext $restContext;
    private JWTManager $jwtManager;
    private IriConverterInterface $iriConverter;
    private ObjectManager $manager;
    private SchemaTool $schemaTool;
    private array $classes;
    private array $components = [];

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct(ManagerRegistry $doctrine, JWTManager $jwtManager, IriConverterInterface $iriConverter)
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
     * @createSchema
     */
    public function createDatabase(): void
    {
        $this->schemaTool->dropSchema($this->classes);
        $this->doctrine->getManager()->clear();
        $this->schemaTool->createSchema($this->classes);
    }

    /**
     * @BeforeScenario
     * @login
     */
    public function login(BeforeScenarioScope $scope): void
    {
        $user = new User();
        $user
            ->setRoles(['ROLE_ADMIN'])
            ->setUsername('admin@admin.com')
            ->setPassword('admin');
        $this->manager->persist($user);
        $this->manager->flush();
        $this->manager->clear();

        $token = $this->jwtManager->create($user);

        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
        $this->restContext->iAddHeaderEqualTo('Authorization', "Bearer $token");
    }

    /**
     * @AfterScenario
     * @logout
     */
    public function logout(): void
    {
        $this->restContext->iAddHeaderEqualTo('Authorization', '');
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
}
