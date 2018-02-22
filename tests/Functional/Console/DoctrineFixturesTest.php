<?php

namespace Silverback\ApiComponentBundle\Tests\Functional\Console;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Silverback\ApiComponentBundle\Command\LoadFixturesCommand;
use Silverback\ApiComponentBundle\Entity\Component\Content\Content;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\ContentFixture;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DoctrineFixturesTest extends WebTestCase
{
    protected static $application;

    /**
     * @var EntityManagerInterface
     */
    protected static $em;

    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        $kernel = static::bootKernel([]);
        $container = $kernel->getContainer();
        self::$em = $container->get('doctrine')->getManager();
    }

    public function test_fixtures_load()
    {
        $application = new Application(static::$kernel);
        $application->add(new LoadFixturesCommand());
        $command = $application->find('api-component-bundle:fixtures:load');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            array(
                'command'  => $command->getName()
            )
        );
        $output = $commandTester->getDisplay();
        $this->assertContains('loading Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures', $output);
    }

    private function getEntities(string $cls)
    {
        return self::$em->getRepository($cls)->findAll();
    }

    public function test_content_fixture()
    {
        $entities = $this->getEntities(Content::class);
        $this->assertCount(1, $entities);

        /** @var $content Content */
        $content = $entities[0];
        $this->assertEquals(ContentFixture::DUMMY_CONTENT, $content->getContent());
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        $schemaTool = new SchemaTool(self::$em);
        $schemaTool->dropSchema(self::$em->getMetadataFactory()->getAllMetadata());
        self::$em->clear();
        self::$em->close();
        self::$em = null; // avoid memory leaks
    }
}
