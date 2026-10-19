<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\EventListener\Api\ComponentPositionEventListener;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ComponentPositionEventListenerTest extends TestCase
{
    public function test_a_get_of_a_page_data_position_varies_on_the_path_header(): void
    {
        $response = $this->respond(self::dynamicPosition(), Request::METHOD_GET, ['Accept']);

        self::assertSame(['Accept', 'path'], $response->getVary());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function responsesLeftAlone(): iterable
    {
        yield 'a static position' => [self::staticPosition(), Request::METHOD_GET];
        yield 'a page data position written with PATCH' => [self::dynamicPosition(), Request::METHOD_PATCH];
        yield 'a page data position in a HEAD request' => [self::dynamicPosition(), Request::METHOD_HEAD];
        yield 'a resource which is not a position' => [new DummyComponent(), Request::METHOD_GET];
        yield 'no data' => [null, Request::METHOD_GET];
    }

    #[DataProvider('responsesLeftAlone')]
    public function test_any_other_response_does_not_vary_on_the_path_header(mixed $data, string $method): void
    {
        $response = $this->respond($data, $method, ['Accept']);

        self::assertSame(['Accept'], $response->getVary());
    }

    private static function dynamicPosition(): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->setPageDataProperty('component');

        return $position;
    }

    private static function staticPosition(): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->component = new DummyComponent();

        return $position;
    }

    /**
     * @param list<string> $vary
     */
    private function respond(mixed $data, string $method, array $vary): Response
    {
        $request = Request::create('/_/component_positions/1', $method);
        $request->attributes->set('data', $data);
        $response = new Response();
        $response->setVary($vary);
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        (new ComponentPositionEventListener())->onPostRespond($event);

        return $event->getResponse();
    }
}
