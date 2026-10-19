<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\ContextBuilder;

use ApiPlatform\State\SerializerContextBuilderInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Serializer\ContextBuilder\TimestampedContextBuilder;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestamped;
use Symfony\Component\HttpFoundation\Request;

class TimestampedContextBuilderTest extends TestCase
{
    public function test_normalization_adds_the_resources_timestamped_read_group(): void
    {
        self::assertSame(['read', 'DummyTimestamped:timestamped:read'], $this->contextGroups(['resource_class' => DummyTimestamped::class, 'groups' => ['read']], true));
    }

    public function test_denormalization_adds_the_resources_timestamped_write_group(): void
    {
        self::assertSame(['write', 'DummyTimestamped:timestamped:write'], $this->contextGroups(['resource_class' => DummyTimestamped::class, 'groups' => ['write']], false));
    }

    public function test_a_context_without_groups_is_left_alone(): void
    {
        self::assertSame(['resource_class' => DummyTimestamped::class, 'groups' => []], $this->context(['resource_class' => DummyTimestamped::class, 'groups' => []], true));
    }

    public function test_a_context_without_a_resource_class_is_left_alone(): void
    {
        self::assertSame(['resource_class' => null, 'groups' => ['read']], $this->context(['resource_class' => null, 'groups' => ['read']], true));
    }

    public function test_a_manifest_context_is_left_alone(): void
    {
        self::assertSame(['Route:manifest:read'], $this->contextGroups(['resource_class' => DummyTimestamped::class, 'groups' => ['Route:manifest:read']], true));
    }

    private function contextGroups(array $decoratedContext, bool $normalization): array
    {
        return $this->context($decoratedContext, $normalization)['groups'];
    }

    private function context(array $decoratedContext, bool $normalization): array
    {
        $request = new Request();
        $decorated = $this->createMock(SerializerContextBuilderInterface::class);
        $decorated->expects(self::once())->method('createFromRequest')->with($request, $normalization, ['attribute' => true])->willReturn($decoratedContext);

        return (new TimestampedContextBuilder($decorated))->createFromRequest($request, $normalization, ['attribute' => true]);
    }
}
