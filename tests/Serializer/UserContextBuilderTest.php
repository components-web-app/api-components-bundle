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

namespace Silverback\ApiComponentsBundle\Tests\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\UserContextBuilder;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class UserContextBuilderTest extends TestCase
{
    private MockObject $serializerContextBuilderMock;
    private AuthorizationCheckerInterface $authorizationCheckerMock;
    private UserContextBuilder $userContextBuilder;

    protected function setUp(): void
    {
        $this->serializerContextBuilderMock = $this->createMock(SerializerContextBuilderInterface::class);
        $this->authorizationCheckerMock = $this->createMock(AuthorizationCheckerInterface::class);
        $this->userContextBuilder = new UserContextBuilder($this->serializerContextBuilderMock, $this->authorizationCheckerMock);
    }

    public function test_request_output_no_resource_class(): void
    {
        $request = new Request();
        $normalization = true;
        $extractedAttributes = ['attr' => 'value'];

        $this->serializerContextBuilderMock
            ->expects(self::once())
            ->method('createFromRequest')
            ->with($request, $normalization, $extractedAttributes)
            ->willReturn(['context_key' => 'context_value']);

        $this->authorizationCheckerMock
            ->expects($this->never())
            ->method('isGranted');

        $this->assertEquals(['context_key' => 'context_value'], $this->userContextBuilder->createFromRequest($request, $normalization, $extractedAttributes));
    }

    public function test_request_output_not_supported_resource_class(): void
    {
        $request = new Request();
        $normalization = true;
        $extractedAttributes = ['attr' => 'value'];

        $this->serializerContextBuilderMock
            ->expects(self::once())
            ->method('createFromRequest')
            ->with($request, $normalization, $extractedAttributes)
            ->willReturn(['context_key' => 'context_value', 'resource_class' => __CLASS__]);

        $this->authorizationCheckerMock
            ->expects($this->never())
            ->method('isGranted');

        $this->assertEquals(['context_key' => 'context_value', 'resource_class' => __CLASS__], $this->userContextBuilder->createFromRequest($request, $normalization, $extractedAttributes));
    }

    public function test_request_output_no_serialization_groups_configured(): void
    {
        $request = new Request();
        $normalization = true;

        $this->serializerContextBuilderMock
            ->expects(self::once())
            ->method('createFromRequest')
            ->with($request, $normalization, null)
            ->willReturn(['resource_class' => User::class]);

        $this->authorizationCheckerMock
            ->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_SUPER_ADMIN')
            ->willReturn(false);

        $this->assertEquals(['groups' => ['User:output'], 'resource_class' => User::class], $this->userContextBuilder->createFromRequest($request, $normalization, null));
    }

    public function test_request_input_merge_with_existing_serialization_groups(): void
    {
        $request = new Request();
        $normalization = false;

        $this->serializerContextBuilderMock
            ->expects(self::once())
            ->method('createFromRequest')
            ->with($request, $normalization, null)
            ->willReturn(['groups' => ['a_group'], 'resource_class' => User::class]);

        $this->authorizationCheckerMock
            ->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_SUPER_ADMIN')
            ->willReturn(false);

        $this->assertEquals(['groups' => ['a_group', 'User:input'], 'resource_class' => User::class], $this->userContextBuilder->createFromRequest($request, $normalization, null));
    }

    public function test_request_input_with_super_admin_groups(): void
    {
        $request = new Request();
        $normalization = false;

        $this->serializerContextBuilderMock
            ->expects(self::once())
            ->method('createFromRequest')
            ->with($request, $normalization, null)
            ->willReturn(['resource_class' => User::class]);

        $this->authorizationCheckerMock
            ->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_SUPER_ADMIN')
            ->willReturn(true);

        $this->assertEquals(['groups' => ['User:input', 'User:superAdmin'], 'resource_class' => User::class], $this->userContextBuilder->createFromRequest($request, $normalization, null));
    }
}
