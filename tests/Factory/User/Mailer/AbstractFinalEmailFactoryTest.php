<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Factory\User\Mailer;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

abstract class AbstractFinalEmailFactoryTest extends TestEmailCase
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
        $loaderMock = $this->createMock(LoaderInterface::class);
        $twig = new Environment($loaderMock);

        $containerWith = [];
        $willReturn = [];

        if ($tokenPathExpected) {
            $requestStackMock = $this->createMock(RequestStack::class);
            $requestStackMock
                ->expects(self::once())
                ->method('getMainRequest')
                ->willReturn(null);

            $refererUrlMock = $this->createMock(RefererUrlResolver::class);
            $refererUrlMock
                ->expects(self::once())
                ->method('getAbsoluteUrl')
                ->with('/default-path')
                ->willReturn('/transformed-path');

            $containerWith[] = [RequestStack::class];
            $containerWith[] = [RefererUrlResolver::class];
            $willReturn[] = $requestStackMock;
            $willReturn[] = $refererUrlMock;
        }

        $containerWith[] = ['twig'];
        $willReturn[] = $twig;

        $this->containerInterfaceMock
            ->expects(self::exactly(\count($willReturn)))
            ->method('get')
            ->withConsecutive(...$containerWith)
            ->willReturnOnConsecutiveCalls(...$willReturn);
    }
}
