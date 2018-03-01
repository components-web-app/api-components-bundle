<?php

namespace Silverback\ApiComponentBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Silverback\ApiComponentBundle\Entity\Content\Component\Form\Form;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Form\FormFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\FormFixture;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FormSubmitTest extends WebTestCase
{
    /**
     * @var Client
     */
    private static $client;
    /**
     * @var SchemaTool
     */
    private static $schemaTool;
    /**
     * @var array
     */
    private static $classes;
    /**
     * @var Form
     */
    private static $form;

    public static function setUpBeforeClass()
    {
        self::$client = static::createClient();
        $container = self::$client->getContainer();
        $doctrine = $container->get('doctrine');
        /** @var EntityManager $entityManager */
        $entityManager = $doctrine->getManager();
        self::$schemaTool = new SchemaTool($entityManager);
        self::$classes = $entityManager->getMetadataFactory()->getAllMetadata();
        self::$schemaTool->createSchema(self::$classes);

        $fixture = new FormFixture($container->get('test.' . FormFactory::class));
        $fixture->load($entityManager);
        self::$form = $entityManager->getRepository(Form::class)->findOneBy([]);
    }

    public function test_patch_form_fail()
    {
        self::$client->request('PATCH', sprintf('/component/forms/%s/submit', self::$form->getId()));
        $this->assertEquals(406, self::$client->getResponse()->getStatusCode());
    }

    public function test_patch_form_success()
    {
        self::$client->request(
            'PATCH',
            sprintf('/component/forms/%s/submit', self::$form->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"test": {"name":"Dummy"}}'
        );
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
    }

    public function test_post_form_fail()
    {
        self::$client->request('POST', sprintf('/component/forms/%s/submit', self::$form->getId()));
        $this->assertEquals(406, self::$client->getResponse()->getStatusCode());
    }

    public function test_post_form_success()
    {
        self::$client->request(
            'POST',
            sprintf('/component/forms/%s/submit', self::$form->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"test": {"name":"Dummy"}}'
        );
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
    }

    public static function tearDownAfterClass()
    {
        self::$schemaTool->dropSchema(self::$classes);
    }
}
