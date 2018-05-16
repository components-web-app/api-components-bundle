<?php

namespace Silverback\ApiComponentBundle\Tests\Functional\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Silverback\ApiComponentBundle\Entity\Content\Component\Form\Form;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\Component\FormFixture;
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
     * @var string
     */
    private static $formRoute;

    /**
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public static function setUpBeforeClass()
    {
        self::$client = static::createClient([], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json'
        ]);
        $container = self::$client->getContainer();
        $doctrine = $container->get('doctrine');
        /** @var EntityManager $entityManager */
        $entityManager = $doctrine->getManager();
        self::$schemaTool = new SchemaTool($entityManager);
        self::$classes = $entityManager->getMetadataFactory()->getAllMetadata();
        self::$schemaTool->createSchema(self::$classes);
        $fixture = $container->get(FormFixture::class);
        $fixture->load($entityManager);
        $form = $entityManager->getRepository(Form::class)->findOneBy([]);
        self::$formRoute = sprintf('/forms/%s/submit', $form->getId());
    }

    public function test_patch_form_fail()
    {
        $this->createInvalidRequest('PATCH');
    }

    public function test_patch_form_success()
    {
        $this->createValidRequest('PATCH');
    }

    public function test_post_form_fail()
    {
        $this->createInvalidRequest('POST');
    }

    public function test_post_form_success()
    {
        $this->createValidRequest('POST');
    }

    private function createValidRequest(string $method)
    {
        self::$client->request(
            $method,
            self::$formRoute,
            [],
            [],
            [],
            '{"test": {"name":"Dummy"}}'
        );
        $content = self::$client->getResponse()->getContent();
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode(), $content);
        $this->assertRequestContainsForm($content);
    }

    private function createInvalidRequest(string $method)
    {
        self::$client->request(
            $method,
            self::$formRoute,
           [],
           [],
           [],
           '{"test": {"name":""}}'
        );
        $content = self::$client->getResponse()->getContent();
        $this->assertEquals(400, self::$client->getResponse()->getStatusCode(), $content);
        $this->assertRequestContainsForm($content);
    }

    private function assertRequestContainsForm($content)
    {
        $decoded = json_decode($content, true);
        $this->assertInternalType('array', $decoded, $content);
        $this->assertArrayHasKey('form', $decoded);
        $this->assertCount(4, $decoded['form']);
        $this->assertArrayHasKey('vars', $decoded['form']);
        $this->assertArrayHasKey('submitted', $decoded['form']['vars']);
        $this->assertTrue($decoded['form']['vars']['submitted']);
    }

    public static function tearDownAfterClass()
    {
        self::$schemaTool->dropSchema(self::$classes);
    }
}
