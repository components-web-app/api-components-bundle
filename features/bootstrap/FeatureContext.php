<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behatch\HttpCall\Request;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, KernelAwareContext
{
    /**
     * @var EntityManagerInterface
     */
    private $manager;
    private $doctrine;
    private $schemaTool;
    private $classes;
    private $request;
    private $propertyAccessor;
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct(ManagerRegistry $doctrine, Request $request)
    {
        $this->doctrine = $doctrine;
        $this->manager = $doctrine->getManager();
        $this->schemaTool = new SchemaTool($this->manager);
        $this->classes = $this->manager->getMetadataFactory()->getAllMetadata();
        $this->request = $request;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Sets the default Accept HTTP header to null (workaround to artificially remove it).
     *
     * @AfterStep
     */
    public function removeAcceptHeaderAfterRequest(AfterStepScope $event)
    {
        if (preg_match('/^I send a "[A-Z]+" request to ".+"/', $event->getStep()->getText())) {
            $this->request->setHttpHeader('Accept', null);
        }
    }

    /**
     * Sets the default Accept HTTP header to null (workaround to artificially remove it).
     *
     * @BeforeScenario
     */
    public function removeAcceptHeaderBeforeScenario()
    {
        $this->request->setHttpHeader('Accept', null);
    }

    /**
     * @BeforeScenario @createSchema
     */
    public function createDatabase()
    {
        $this->schemaTool->createSchema($this->classes);
    }

    /**
     * @AfterScenario @dropSchema
     */
    public function dropDatabase()
    {
        $this->schemaTool->dropSchema($this->classes);
        $this->doctrine->getManager()->clear();
    }

    /**
     * @Then the service :service should have property :property with a value of :value
     */
    public function assertNodeValueIs(string $service, string $property, string $value)
    {
        Assert::assertEquals(
            $this->propertyAccessor->getValue($this->kernel->getContainer()->get($service), $property),
            $value
        );
    }
}
