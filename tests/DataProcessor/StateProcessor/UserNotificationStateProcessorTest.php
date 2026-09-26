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
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Helper\User\UserWriteNotifier;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

class UserNotificationStateProcessorTest extends TestCase
{
    public function test_a_created_user_gets_the_welcome_email_after_the_write_even_with_previous_data(): void
    {
        $user = $this->user();
        $calls = [];
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'write';

            return 'written';
        });
        $mailer = $this->createMock(UserMailer::class);
        $mailer->expects(self::once())->method('sendWelcomeEmail')->with($user)->willReturnCallback(static function () use (&$calls): bool {
            $calls[] = 'welcome';

            return true;
        });
        $mailer->expects(self::never())->method('sendUsernameChangedEmail');

        $result = (new UserNotificationStateProcessor($inner, new UserWriteNotifier($mailer)))->process($user, new Post(), [], ['previous_data' => $this->user('other')]);

        self::assertSame('written', $result);
        self::assertSame(['write', 'welcome'], $calls);
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
        $mailer = $this->createMock(UserMailer::class);
        $mailer->expects(self::once())->method('sendUsernameChangedEmail');
        $mailer->expects(self::never())->method('sendWelcomeEmail');

        (new UserNotificationStateProcessor($this->inner(), new UserWriteNotifier($mailer)))->process($this->user('renamed'), $operation, [], ['previous_data' => $this->user()]);
    }

    public function test_an_update_without_previous_user_data_gets_the_welcome_email(): void
    {
        $mailer = $this->createMock(UserMailer::class);
        $mailer->expects(self::once())->method('sendWelcomeEmail');

        (new UserNotificationStateProcessor($this->inner(), new UserWriteNotifier($mailer)))->process($this->user(), new Patch(), [], ['previous_data' => new Route()]);
    }

    /**
     * @return iterable<string, array{mixed, Operation}>
     */
    public static function ignored(): iterable
    {
        yield 'a deleted user' => [new User('daniel'), new Delete()];
        yield 'a read user' => [new User('daniel'), new Get()];
        yield 'something other than a user' => [new Route(), new Post()];
        yield 'a user through an operation that is not HTTP' => [new User('daniel'), new Mutation()];
    }

    #[DataProvider('ignored')]
    public function test_nothing_is_sent_for(mixed $data, Operation $operation): void
    {
        $mailer = $this->createMock(UserMailer::class);
        $mailer->expects(self::never())->method('sendWelcomeEmail');

        self::assertSame('written', (new UserNotificationStateProcessor($this->inner(), new UserWriteNotifier($mailer)))->process($data, $operation));
    }

    public function test_the_inner_processor_receives_the_data_operation_uri_variables_and_context(): void
    {
        $user = $this->user();
        $operation = new Post();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($user, $operation, ['id' => 1], ['previous_data' => null]);

        (new UserNotificationStateProcessor($inner, new UserWriteNotifier($this->createStub(UserMailer::class))))->process($user, $operation, ['id' => 1], ['previous_data' => null]);
    }

    private function user(string $username = 'daniel'): User
    {
        $user = new User($username, 'daniel@example.com');
        $user->setNewEmailConfirmationToken(null);

        return $user;
    }

    private function inner(): ProcessorInterface
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn('written');

        return $inner;
    }
}
