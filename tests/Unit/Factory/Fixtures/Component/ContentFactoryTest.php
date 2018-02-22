<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Fixtures\Component;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Silverback\ApiComponentBundle\Factory\Fixtures\Component\ContentFactory;

class ContentFactoryTest extends AbstractFactoryTest
{
    /**
     * @var ContentFactory
     */
    private $componentFactory;

    public function setUp()
    {
        $args = $this->getConstructorArgs();

        $mock = new MockHandler([new Response(200, [], '<p>Mocked Lipsum Return</p>')]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $args[] = $client;

        $this->componentFactory = new ContentFactory(...$args);
    }

    public function test_invalid_option()
    {
        $this->expectException(InvalidFactoryOptionException::class);
        $this->componentFactory->create(
            [
                'invalid' => null
            ]
        );
    }

    public function test_create_lipsum_with_page_owner()
    {
        $component = $this->componentFactory->create(
            [
                'lipsum' => ['1', 'short']
            ],
            new Page()
        );
        $this->assertEquals('<p>Mocked Lipsum Return</p>', $component->getContent());
    }

    public function test_create_custom_with_component_group_owner()
    {
        $ops = [
            'content' => 'ABCDEFG',
            'className' => 'custom-class'
        ];
        $component = $this->componentFactory->create(
            $ops,
            new ComponentGroup()
        );
        $this->assertEquals($ops['content'], $component->getContent());
        $this->assertEquals($ops['className'], $component->getClassName());
    }
}
