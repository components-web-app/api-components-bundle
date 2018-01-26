<?php

namespace Silverback\ApiComponentBundle\Tests\Console;

use Silverback\ApiComponentBundle\Command\LoadFixturesCommand;
use Silverback\ApiComponentBundle\Entity\Component\Content;
use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList;
use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextListItem;
use Silverback\ApiComponentBundle\Entity\Component\Hero;
use Silverback\ApiComponentBundle\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DoctrineFixturesTest extends WebTestCase
{
    protected static $application;
    protected static $em;

    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass ()
    {
        parent::setUpBeforeClass();
        $kernel = static::bootKernel([]);
        $container = $kernel->getContainer();
        self::$em = $container->get('doctrine')->getManager();
    }

    public function test_fixtures_load ()
    {
        $application = new Application(static::$kernel);
        $application->add(new LoadFixturesCommand());
        $command = $application->find('api-component-bundle:fixtures:load');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
                                    'command'  => $command->getName()
                                ));
        $output = $commandTester->getDisplay();
        $this->assertContains('loading Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures', $output);
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
        $this->assertEquals(count($page->getComponents()), 4);
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

    public function test_fixture_feature ()
    {
        $entities = $this->getEntities(FeatureTextList::class);
        $this->assertCount(1, $entities);

        /**
         * @var $feature FeatureTextList
         */
        $feature = $entities[0];
        $this->assertEquals($feature->getSort(), 3);

        $entities = $this->getEntities(FeatureTextListItem::class);
        $this->assertCount(1, $entities);
        /**
         * @var $item FeatureTextListItem
         */
        $item = $entities[0];
        $this->assertEquals($item->getLabel(), 'Feature label');
        $this->assertEquals($item->getLink(), '/');
        $this->assertEquals($item->getFeature(), $feature);
        $this->assertEquals($item->getSortOrder(), 1);
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        self::$em->close();
        self::$em = null; // avoid memory leaks
    }
}
