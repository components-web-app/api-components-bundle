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

class AbstractFinalEmailFactoryTestCase extends TestEmailCase
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

    protected function assertCommonMockMethodsCalled(bool $tokenPathExpected = false, array $additionalExpectations = []): void
    {
        $loaderMock = $this->createMock(LoaderInterface::class);
        $twig = new Environment($loaderMock);

        $expectations = [...$additionalExpectations];

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

            $expectations[] = [
                [RequestStack::class],
                $requestStackMock,
            ];
            $expectations[] = [
                [RefererUrlResolver::class],
                $refererUrlMock,
            ];
        }

        $expectations[] = [
            ['twig'],
            $twig,
        ];

        $invokedCount = self::exactly(\count($expectations));

        $this->containerInterfaceMock
            ->expects($invokedCount)
            ->method('get')
            ->willReturnCallback(function (...$parameters) use ($invokedCount, $expectations) {
                $currentInvocationCount = $invokedCount->numberOfInvocations();
                $currentExpectation = $expectations[$currentInvocationCount - 1];
                $this->assertSame($currentExpectation[0], $parameters);

                return $currentExpectation[1];
            });
    }
}
