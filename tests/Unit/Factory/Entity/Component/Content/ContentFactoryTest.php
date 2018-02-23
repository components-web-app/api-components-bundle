<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Content;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Content\ContentFactory;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

/**
 *
 * @author Daniel West <daniel@silverback.is
 * @property ContentFactory $factory
 */
class ContentFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = ContentFactory::class;
        $this->testOps = [
            'content' => 'ABCDEFG'
        ];
        parent::setUp();
    }

    public function getConstructorArgs(): array
    {
        $args = parent::getConstructorArgs();
        $mock = new MockHandler([new Response(200, [], '<p>Mocked Lipsum Return</p>')]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $args[] = $client;
        return $args;
    }

    public function test_create_lipsum_with_page_owner()
    {
        $component = $this->factory->create(
            [
                'lipsum' => ['1', 'short']
            ]
        );
        $this->assertEquals('<p>Mocked Lipsum Return</p>', $component->getContent());
    }
}
