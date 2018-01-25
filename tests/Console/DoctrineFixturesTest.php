<?php

namespace Silverback\ApiComponentBundle\Tests\Console;

use Silverback\ApiComponentBundle\Entity\Component\Content;
use Silverback\ApiComponentBundle\Entity\Component\Hero;
use Silverback\ApiComponentBundle\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\StringInput;

class DoctrineFixturesTest extends WebTestCase
{
    protected static $container;
    protected static $application;
    protected static $em;

    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass ()
    {
        parent::setUpBeforeClass();
        self::runCommand('api-component-bundle:fixtures:load');
        $container = static::$kernel->getContainer();
        self::$em = $container->get('doctrine')->getManager();
    }

    /**
     * @param $command
     * @return int
     * @throws \Exception
     */
    protected static function runCommand($command)
    {
        $command = sprintf('%s --quiet', $command);

        return self::getApplication()->run(new StringInput($command));
    }

    /**
     * @return Application
     */
    protected static function getApplication()
    {
        if (null === self::$application) {
            $client = static::createClient();
            $kernel = $client->getKernel();
            self::$application = new Application($kernel);
            self::$application->setAutoExit(false);
        }

        return self::$application;
    }

    private function getEntities (string $cls)
    {
        $entities = self::$em->getRepository($cls)->findAll();
        return $entities;
    }

    public function test_fixture_page ()
    {

        $entities = $this->getEntities(Page::class);
        $this->assertCount(1, $entities);

        /**
         * @var $page Page
         */
        $page = $entities[0];
        $this->assertEquals(count($page->getComponents()), 3);
        $this->assertEquals($page->getTitle(), 'Dummy Title');
        $this->assertEquals($page->getMetaDescription(), 'Dummy Meta Description');
        $this->assertEquals($page->getRoutes()->first()->getRoute(), '/');
    }

    public function test_fixture_hero ()
    {
        $entities = $this->getEntities(Hero::class);
        $this->assertCount(1, $entities);

        /**
         * @var $hero Hero
         */
        $hero = $entities[0];
        $this->assertEquals($hero->getTitle(), 'T');
        $this->assertEquals($hero->getSubtitle(), 'ST');
    }

    public function test_fixture_content ()
    {
        $entities = $this->getEntities(Content::class);
        $this->assertCount(1, $entities);

        /**
         * @var $content Content
         */
        $content = $entities[0];
        $this->assertEquals($content->getContent(), 'Dummy content');
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        self::$em->close();
        self::$em = null; // avoid memory leaks
    }
}
