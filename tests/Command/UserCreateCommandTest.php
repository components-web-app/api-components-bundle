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
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
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

    public function test_missing_arguments_are_asked_for_with_the_question_helper(): void
    {
        $factory = $this->createMock(UserFactory::class);
        $factory->expects(self::once())->method('create')->with('bob', 'pass', 'bob@example.com');

        $command = new UserCreateCommand($factory);
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $tester = new CommandTester($command);
        $tester->setInputs(['bob', 'bob@example.com', 'pass']);
        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
    }

    public function test_asking_for_missing_arguments_fails_clearly_without_a_question_helper(): void
    {
        $factory = $this->createMock(UserFactory::class);
        $factory->expects(self::never())->method('create');

        $helperSet = new HelperSet();
        $helperSet->set(new FormatterHelper(), 'question');
        $command = new UserCreateCommand($factory);
        $command->setHelperSet($helperSet);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "question" helper must be an instance of %s to ask for missing arguments, %s given.', QuestionHelper::class, FormatterHelper::class));
        (new CommandTester($command))->execute([]);
    }

    public function test_no_question_helper_is_needed_when_every_argument_is_given(): void
    {
        $factory = $this->createMock(UserFactory::class);
        $factory->expects(self::once())->method('create');

        $helperSet = new HelperSet();
        $helperSet->set(new FormatterHelper(), 'question');
        $command = new UserCreateCommand($factory);
        $command->setHelperSet($helperSet);
        $status = (new CommandTester($command))->execute(['username' => 'bob', 'email' => 'bob@example.com', 'password' => 'pass']);

        self::assertSame(Command::SUCCESS, $status);
    }
}
