<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Factory\Mailer\User;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Url\RefererUrlHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

abstract class AbstractFinalEmailFactoryTest extends TestCase
{
    /**
     * @var MockObject|ContainerInterface
     */
    protected MockObject $containerInterfaceMock;
    /**
     * @var MockObject|EventDispatcherInterface
     */
    protected MockObject $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->containerInterfaceMock = $this->createMock(ContainerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
    }

    protected function assertCommonMockMethodsCalled(bool $tokenPathExpected = false): void
    {
        $callIndex = 0;
        if ($tokenPathExpected) {
            $requestStackMock = $this->createMock(RequestStack::class);
            $requestStackMock
                ->expects($this->once())
                ->method('getMasterRequest')
                ->willReturn(null);

            $this->containerInterfaceMock
                ->expects($this->at($callIndex))
                ->method('get')
                ->with(RequestStack::class)
                ->willReturn($requestStackMock);
            ++$callIndex;

            $refererUrlMock = $this->createMock(RefererUrlHelper::class);
            $refererUrlMock
                ->expects($this->once())
                ->method('getAbsoluteUrl')
                ->with('/default-path')
                ->willReturn('/transformed-path');

            $this->containerInterfaceMock
                ->expects($this->at($callIndex))
                ->method('get')
                ->with(RefererUrlHelper::class)
                ->willReturn($refererUrlMock);
            ++$callIndex;
        }

        $loaderMock = $this->createMock(LoaderInterface::class);
        $twig = new Environment($loaderMock);

        $this->containerInterfaceMock
            ->expects($this->at($callIndex))
            ->method('get')
            ->with(Environment::class)
            ->willReturn($twig);
    }
}
