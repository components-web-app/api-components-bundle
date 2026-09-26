<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\UserNotificationStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

class UserNotificationStateProcessorTest extends TestCase
{
    public function test_a_created_user_is_notified_as_new_after_the_write_even_with_previous_data(): void
    {
        $user = new User();
        $calls = [];
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'write';

            return 'written';
        });
        $listener = $this->createMock(UserEventListener::class);
        $listener->expects(self::once())->method('postWrite')->with($user, null)->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'notify';
        });

        $result = (new UserNotificationStateProcessor($inner, $listener))->process($user, new Post(), [], ['previous_data' => new User()]);

        self::assertSame('written', $result);
        self::assertSame(['write', 'notify'], $calls);
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function updates(): iterable
    {
        yield 'PATCH' => [new Patch()];
        yield 'PUT' => [new Put()];
    }

    #[DataProvider('updates')]
    public function test_an_updated_user_is_compared_with_its_previous_data(Operation $operation): void
    {
        $user = new User();
        $previous = new User();
        $listener = $this->createMock(UserEventListener::class);
        $listener->expects(self::once())->method('postWrite')->with($user, $previous);

        (new UserNotificationStateProcessor($this->inner(), $listener))->process($user, $operation, [], ['previous_data' => $previous]);
    }

    public function test_an_update_without_previous_user_data_is_notified_as_new(): void
    {
        $user = new User();
        $listener = $this->createMock(UserEventListener::class);
        $listener->expects(self::once())->method('postWrite')->with($user, null);

        (new UserNotificationStateProcessor($this->inner(), $listener))->process($user, new Patch(), [], ['previous_data' => new Route()]);
    }

    /**
     * @return iterable<string, array{mixed, Operation}>
     */
    public static function ignored(): iterable
    {
        yield 'a deleted user' => [new User(), new Delete()];
        yield 'a read user' => [new User(), new Get()];
        yield 'something other than a user' => [new Route(), new Post()];
        yield 'a user through an operation that is not HTTP' => [new User(), new Mutation()];
    }

    #[DataProvider('ignored')]
    public function test_nothing_is_sent_for(mixed $data, Operation $operation): void
    {
        $listener = $this->createMock(UserEventListener::class);
        $listener->expects(self::never())->method('postWrite');

        self::assertSame('written', (new UserNotificationStateProcessor($this->inner(), $listener))->process($data, $operation));
    }

    public function test_the_inner_processor_receives_the_data_operation_uri_variables_and_context(): void
    {
        $user = new User();
        $operation = new Post();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($user, $operation, ['id' => 1], ['previous_data' => null]);

        (new UserNotificationStateProcessor($inner, $this->createStub(UserEventListener::class)))->process($user, $operation, ['id' => 1], ['previous_data' => null]);
    }

    private function inner(): ProcessorInterface
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn('written');

        return $inner;
    }
}
