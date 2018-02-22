<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Fixtures\Component;

use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Factory\Fixtures\Component\ContentFactory;

class ContentFactoryTest extends TestCase
{
    /**
     * @var ContentFactory
     */
    private $component;

    public function setUp()
    {
        /** @var ObjectManager $objectManagerMock */
        $objectManagerMock = $this
            ->getMockBuilder(ObjectManager::class)
            ->getMock()
        ;

        $mock = new MockHandler([new Response(200, [], '<p>Mocked Lipsum Return</p>')]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->component = new ContentFactory(
            $objectManagerMock,
            $client
        );
    }

    public function test_create_lipsum()
    {
        $component = $this->component->create([
            'lipsum' => ['1', 'short']
        ], new Page());
        $this->assertEquals('<p>Mocked Lipsum Return</p>', $component->getContent());
    }

    public function test_create_custom()
    {
        $component = $this->component->create([
            'content' => 'ABCDEFG',
            'className' => 'custom-class'
        ], new Page());
        $this->assertEquals('ABCDEFG', $component->getContent());
        $this->assertEquals('custom-class', $component->getClassName());
    }
}
