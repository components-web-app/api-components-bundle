<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Validator\Contraints;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Component\Article\Article;
use Silverback\ApiComponentBundle\Entity\Component\Content\Content;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Content\ComponentLocation as ComponentLocationEntity;
use Silverback\ApiComponentBundle\Tests\TestBundle\Entity\FileComponent;
use Silverback\ApiComponentBundle\Validator\Constraints\ComponentLocation;
use Silverback\ApiComponentBundle\Validator\Constraints\ComponentLocationValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ComponentLocationValidatorTest extends TestCase
{
    /**
     * @var ComponentLocationValidator
     */
    private $componentLocationValidator;
    /**
     * @var MockObject|ExecutionContextInterface
     */
    private $context;
    /**
     * @var MockObject|ComponentLocation
     */
    private $constraint;
    /**
     * @var MockObject|ComponentLocationEntity
     */
    private $entity;
    /**
     * @var MockObject|ComponentGroup
     */
    private $content;

    public function setUp()
    {
        $this->componentLocationValidator = new ComponentLocationValidator();
        $this->context = $this->getMockBuilder(ExecutionContextInterface::class)->getMock();
        $this->componentLocationValidator->initialize($this->context);
        $this->constraint = $this->getMockBuilder(ComponentLocation::class)->getMock();
        $this->entity = $this->getMockBuilder(ComponentLocationEntity::class)->getMock();
        $this->content = $this->getMockBuilder(ComponentGroup::class)->getMock();
    }

    /**
     * @throws \ReflectionException
     */
    public function test_validate_invalid_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->componentLocationValidator->validate(new FileComponent(), $this->constraint);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_valid_component_location(): void
    {
        $this->setUpForFullValidationChecks(new Article());
        $this->context
            ->expects($this->never())
            ->method('buildViolation')
        ;
        $this->componentLocationValidator->validate($this->entity, $this->constraint);
    }

    /**
     * @throws \ReflectionException
     */
    public function test_invalid_component_location(): void
    {
        $violation = $this
            ->getMockBuilder(ConstraintViolationBuilderInterface::class)
            ->getMock()
        ;
        $violation->expects($this->once())->method('atPath')->with('component')->willReturn($violation);
        $violation->expects($this->once())->method('setParameter')->willReturn($violation);

        $this->setUpForFullValidationChecks(new Content());
        $this->context
            ->expects($this->once())
            ->method('buildViolation')
            ->willReturn($violation)
        ;
        $this->componentLocationValidator->validate($this->entity, $this->constraint);
    }

    /**
     * @param $component
     */
    private function setUpForFullValidationChecks($component): void
    {
        $this->content
            ->expects($this->once())
            ->method('getValidComponents')
            ->willReturn(new ArrayCollection([Article::class]))
        ;
        $this->entity
            ->expects($this->once())
            ->method('getComponent')
            ->willReturn($component)
        ;
        $this->entity
            ->expects($this->once())
            ->method('getContent')
            ->willReturn($this->content)
        ;
    }
}
