<?php

use Behat\Behat\Context\Context;
use Behatch\HttpCall\Request;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the specific context.
 */
class EntityManagerContext implements Context
{
    /**
     * @var EntityManagerInterface
     */
    private $manager;
    private $doctrine;
    private $schemaTool;
    private $classes;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     * @param ManagerRegistry $doctrine
     * @param Request $request
     */
    public function __construct(ManagerRegistry $doctrine, Request $request)
    {
        $this->doctrine = $doctrine;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
    }

    /**
     * @BeforeScenario @createSchema
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public function createDatabase()
    {
        $this->schemaTool->updateSchema($this->classes);
    }

    /**
     * @When drop the schema
     * @AfterScenario @dropSchema
     */
    public function dropDatabase()
    {
        $this->schemaTool->dropSchema($this->classes);
        $this->doctrine->getManager()->clear();
    }

    /**
     * @Then the database schema should be valid
     */
    public function schemaIsValid()
    {
        $validator = new SchemaValidator($this->manager);
        $errors = $validator->validateMapping();
        Assert::assertCount(0, $errors, json_encode($errors, JSON_PRETTY_PRINT));
    }

    /**
     * @Then the table :table should exist
     */
    public function tableExists(string $table)
    {
        Assert::assertTrue($this->manager->getConnection()->getSchemaManager()->tablesExist([$table]));
    }

    /**
     * @Then there should be :total tables in the database
     */
    public function tableTotal(int $total)
    {
        Assert::assertCount($total, $this->manager->getConnection()->getSchemaManager()->listTables());
    }
}
