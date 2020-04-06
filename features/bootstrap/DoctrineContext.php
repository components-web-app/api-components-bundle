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

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behatch\Context\RestContext;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ObjectManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity\User;

final class DoctrineContext implements Context
{
    private ManagerRegistry $doctrine;
    private RestContext $restContext;
    private JWTManager $jwtManager;
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
    public function __construct(ManagerRegistry $doctrine, JWTManager $jwtManager)
    {
        $this->doctrine = $doctrine;
        $this->jwtManager = $jwtManager;
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
}
