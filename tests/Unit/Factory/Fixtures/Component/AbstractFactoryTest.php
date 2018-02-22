<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Fixtures\Component;

use Doctrine\Common\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractFactoryTest extends TestCase
{
    protected function getConstructorArgs()
    {
        /** @var ObjectManager $objectManagerMock */
        $objectManagerMock = $this
            ->getMockBuilder(ObjectManager::class)
            ->getMock()
        ;

        $validator = $this
            ->getMockBuilder(ValidatorInterface::class)
            ->getMock()
        ;
        $validator
            ->method('validate')
            ->will($this->returnValue(new ConstraintViolationList()))
        ;

        return [
            $objectManagerMock,
            $validator
        ];
    }
}
