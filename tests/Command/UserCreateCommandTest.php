<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\UserCreateCommand;
use Silverback\ApiComponentsBundle\Factory\User\UserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class UserCreateCommandTest extends TestCase
{
    public function test_violations_are_printed_and_the_command_fails(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Sorry, that user already exists in the database.', null, [], null, 'username', 'bob'),
            new ConstraintViolation('This value is not a valid email address.', null, [], null, 'emailAddress', 'nope'),
        ]);
        $factory = $this->createStub(UserFactory::class);
        $factory->method('create')->willThrowException(new ValidationFailedException(new \stdClass(), $violations));

        $tester = new CommandTester(new UserCreateCommand($factory));
        $status = $tester->execute(['username' => 'bob', 'email' => 'nope', 'password' => 'pass'], ['interactive' => false]);

        self::assertSame(Command::FAILURE, $status);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Could not create user: bob', $display);
        self::assertStringContainsString('username: Sorry, that user already exists in the database.', $display);
        self::assertStringContainsString('emailAddress: This value is not a valid email address.', $display);
        self::assertStringNotContainsString('Created user', $display);
    }

    public function test_a_created_user_succeeds(): void
    {
        $factory = $this->createMock(UserFactory::class);
        $factory->expects(self::once())->method('create')->with('bob', 'pass', 'bob@example.com', false, false, false, true);

        $tester = new CommandTester(new UserCreateCommand($factory));
        $status = $tester->execute(['username' => 'bob', 'email' => 'bob@example.com', 'password' => 'pass', '--overwrite' => true], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('Created user: bob', $tester->getDisplay());
    }
}
