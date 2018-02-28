<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Repository\LayoutRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

class LayoutRepositoryTest extends TestCase
{
    public function test_repository_class()
    {
        $classMetadataMock = $this->getMockBuilder(ClassMetadata::class)->disableOriginalConstructor()->getMock();

        $objectManagerMock = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $objectManagerMock
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with(Layout::class)
            ->willReturn($classMetadataMock)
        ;

        /** @var MockObject|RegistryInterface $mockRegistryInterface */
        $mockRegistryInterface = $this->getMockBuilder(RegistryInterface::class)->getMock();
        $mockRegistryInterface
            ->expects($this->once())
            ->method('getManagerForClass')
            ->with(Layout::class)
            ->willReturn($objectManagerMock)
        ;

        new LayoutRepository($mockRegistryInterface);
    }
}
