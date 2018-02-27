<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Serializer;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Serializer\ApiContextBuilder;
use Symfony\Component\HttpFoundation\Request;

class ApiContextBuilderTest extends TestCase
{
    /**
     * @var ApiContextBuilder
     */
    private $apiContextBuilder;
    /**
     * @var MockObject|SerializerContextBuilderInterface
     */
    private $serializerContextBuilderMock;

    public function setUp()
    {
        $this->serializerContextBuilderMock = $this->getMockBuilder(SerializerContextBuilderInterface::class)->getMock();
        $this->apiContextBuilder = new ApiContextBuilder($this->serializerContextBuilderMock);
    }

    public function test_group_none()
    {
        $request = $this->getRequestForResource(AbstractComponent::class);

        $this->serializerContextBuilderMock
            ->expects($this->once())
            ->method('createFromRequest')
            ->with($request, true, null)
            ->willReturn(
                [
                    'context' => [
                        'item_operation_name' => 'get'
                    ],
                    'groups' => [
                        'none'
                    ]
                ]
            )
        ;

        $context = $this->apiContextBuilder->createFromRequest($request, true);
        $this->assertArrayHasKey('groups', $context);
        $this->assertEquals(['none'], $context['groups']);
    }

    public function test_read_groups()
    {
        $class = key(ApiContextBuilder::CLASS_GROUP_MAPPING);
        $groups = ApiContextBuilder::CLASS_GROUP_MAPPING[$class];
        $request = $this->getRequestForResource($class);
        $this->serializerContextBuilderMock
            ->expects($this->once())
            ->method('createFromRequest')
            ->with($request, true, null)
            ->willReturn([])
        ;
        $context = $this->apiContextBuilder->createFromRequest($request, true);
        $this->assertGroupsOk($context);
        $this->assertReadGroups($context['groups'], $groups);
    }

    public function test_read_groups_merged()
    {
        $class = key(ApiContextBuilder::CLASS_GROUP_MAPPING);
        $groups = ApiContextBuilder::CLASS_GROUP_MAPPING[$class];
        $request = $this->getRequestForResource($class);
        $this->serializerContextBuilderMock
            ->expects($this->once())
            ->method('createFromRequest')
            ->with($request, true, null)
            ->willReturn([ 'groups' => [ 'dummy' ] ])
        ;
        $context = $this->apiContextBuilder->createFromRequest($request, true);
        $this->assertGroupsOk($context);
        $this->assertReadGroups($context['groups'], $groups);
        $this->assertContains('dummy', $context['groups']);
    }

    public function test_write_groups()
    {
        $class = key(ApiContextBuilder::CLASS_GROUP_MAPPING);
        $groups = ApiContextBuilder::CLASS_GROUP_MAPPING[$class];
        $request = $this->getRequestForResource($class);
        $this->serializerContextBuilderMock
            ->expects($this->once())
            ->method('createFromRequest')
            ->with($request, false, null)
            ->willReturn([])
        ;
        $context = $this->apiContextBuilder->createFromRequest($request, false);
        $this->assertGroupsOk($context);
        foreach ($groups as $group) {
            $this->assertContains($group, $context['groups']);
            $this->assertContains(sprintf('%s_write', $group), $context['groups']);
        }
    }

    private function getRequestForResource(string $resourceClassName)
    {
        return new Request([], [], [
            '_api_resource_class' => AbstractComponent::class
        ], [], [], [], '');
    }

    private function assertGroupsOk($context)
    {
        $this->assertArrayHasKey('groups', $context);
        $this->assertInternalType('array', $context['groups']);
        $this->assertContains('default', $context['groups']);
    }

    private function assertReadGroups($contextGroups, $groups)
    {
        foreach ($groups as $group) {
            $this->assertContains($group, $contextGroups);
            $this->assertContains(sprintf('%s_read', $group), $contextGroups);
        }
    }
}
