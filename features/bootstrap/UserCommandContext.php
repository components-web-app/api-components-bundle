<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use Behat\Behat\Context\Context;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Command\UserCreateCommand;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCommandContext implements Context
{
    private ObjectManager $manager;
    private ?int $statusCode = null;
    private string $output = '';

    public function __construct(private readonly UserCreateCommand $userCreateCommand, ManagerRegistry $doctrine)
    {
        $this->manager = $doctrine->getManager();
    }

    /**
     * @When /^I run the user create command with the username "([^"]*)" email "([^"]*)" and password "([^"]*)"( with overwrite|)$/
     */
    public function iRunTheUserCreateCommand(string $username, string $email, string $password, string $overwrite = ''): void
    {
        $tester = new CommandTester($this->userCreateCommand);
        $this->statusCode = $tester->execute(
            [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                '--overwrite' => '' !== $overwrite,
            ],
            ['interactive' => false]
        );
        $this->output = $tester->getDisplay();
        $this->manager->clear();
    }

    /**
     * @Then the user create command should succeed
     */
    public function theCommandShouldSucceed(): void
    {
        if (Command::SUCCESS !== $this->statusCode) {
            throw new \RuntimeException(\sprintf('Expected the command to succeed but it returned %s. Output: %s', var_export($this->statusCode, true), $this->output));
        }
    }

    /**
     * @Then the user create command should fail
     */
    public function theCommandShouldFail(): void
    {
        if (Command::FAILURE !== $this->statusCode) {
            throw new \RuntimeException(\sprintf('Expected the command to fail but it returned %s. Output: %s', var_export($this->statusCode, true), $this->output));
        }
    }

    /**
     * @Then the user create command output should contain :text
     */
    public function theCommandOutputShouldContain(string $text): void
    {
        if (!str_contains($this->output, $text)) {
            throw new \RuntimeException(\sprintf('Expected the command output to contain "%s". Output: %s', $text, $this->output));
        }
    }

    /**
     * @Then /^there should be (?P<count>\d+) users? with the username "(?P<username>[^"]*)"$/
     */
    public function thereShouldBeUsersWithTheUsername(int $count, string $username): void
    {
        $this->manager->clear();
        $users = $this->manager->getRepository(User::class)->findBy(['username' => $username]);
        if ($count !== \count($users)) {
            throw new \RuntimeException(\sprintf('Expected %d user(s) with the username "%s" but found %d', $count, $username, \count($users)));
        }
    }

    /**
     * @Then the user :username should have the email address :emailAddress
     */
    public function theUserShouldHaveTheEmailAddress(string $username, string $emailAddress): void
    {
        $this->manager->clear();
        $user = $this->manager->getRepository(User::class)->findOneBy(['username' => $username]);
        if (null === $user || $user->getEmailAddress() !== $emailAddress) {
            throw new \RuntimeException(\sprintf('Expected the user "%s" to have the email address "%s"', $username, $emailAddress));
        }
    }
}
