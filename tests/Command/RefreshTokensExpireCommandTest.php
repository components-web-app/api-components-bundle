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
use Silverback\ApiComponentsBundle\Command\RefreshTokensExpireCommand;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;

class RefreshTokensExpireCommandTest extends TestCase
{
    public function test_every_field_named_in_the_field_option_help_is_a_user_property(): void
    {
        $description = $this->command()->getDefinition()->getOption('field')->getDescription();

        self::assertSame(1, preg_match('/\(([^)]+)\)/', $description, $matches));
        foreach (array_map('trim', explode(',', $matches[1])) as $field) {
            self::assertTrue(property_exists(AbstractUser::class, $field), \sprintf('The --field help names "%s", which is not a property of %s', $field, AbstractUser::class));
        }
    }

    public function test_the_help_names_the_command_by_its_registered_name(): void
    {
        $command = $this->command();

        self::assertStringContainsString(\sprintf('The <info>%s</info> command', $command->getName()), $command->getProcessedHelp());
    }

    private function command(): RefreshTokensExpireCommand
    {
        return new RefreshTokensExpireCommand($this->createStub(RefreshTokenStorageInterface::class), $this->createStub(UserRepositoryInterface::class));
    }
}
