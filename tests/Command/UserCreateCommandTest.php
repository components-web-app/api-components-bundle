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

namespace Silverback\ApiComponentsBundle\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Repository\AbstractRepositoryTest;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

class UserCreateCommandTest extends AbstractRepositoryTest
{
    /**
     * @var EntityManagerInterface|ObjectManager|null
     */
    protected $entityManager;
    private Command $command;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();
        $this->managerRegistry = $container->get('doctrine');
        $this->clearSchema($this->managerRegistry);
        $application = new Application($kernel);
        $this->command = $application->find('silverback:api-components:user:create');
    }

    public function test_execute_defaults_with_inputs(): void
    {
        $commandTester = new CommandTester($this->command);

        $commandTester->setInputs(['daniel', 'daniel@silverback.is', 'password']);
        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Created user: daniel', $output);

        $repo = $this->entityManager->getRepository(AbstractUser::class);
        /** @var AbstractUser|null $user */
        $user = $repo
            ->findOneBy(
                [
                    'username' => 'daniel',
                ]
            );
        $this->assertNotNull($user);

        $this->assertEquals('daniel@silverback.is', $user->getEmailAddress());
        $this->assertTrue($user->isEnabled());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    public function test_execute_arguments_and_options(): void
    {
        $commandTester = new CommandTester($this->command);

        $existingUser = new User();
        $existingUser->setUsername('daniel')->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($existingUser);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $commandTester->execute(
            [
                'username' => 'daniel',
                'email' => 'daniel@silverback.is',
                'password' => 'password',
                '--inactive' => null,
                '--super-admin' => null,
                '--overwrite' => null,
            ]
        );

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Created user: daniel', $output);

        $repo = $this->entityManager->getRepository(AbstractUser::class);
        /** @var AbstractUser|null $user */
        $user = $repo
            ->findOneBy(
                [
                    'username' => 'daniel',
                ]
            );
        $this->assertNotNull($user);

        $this->assertEquals($existingUser->getId(), $user->getId());
        $this->assertEquals('daniel@silverback.is', $user->getEmailAddress());
        $this->assertFalse($user->isEnabled());
        $this->assertEquals(['ROLE_SUPER_ADMIN'], $user->getRoles());
    }

    public function test_required_username(): void
    {
        $commandTester = new CommandTester($this->command);

        $this->expectMissingInputException();
        $commandTester->execute([]);
    }

    public function test_required_email(): void
    {
        $commandTester = new CommandTester($this->command);

        $this->expectMissingInputException();
        $commandTester->execute(
            [
                'username' => 'daniel',
            ]
        );
    }

    public function test_required_password(): void
    {
        $commandTester = new CommandTester($this->command);

        $this->expectMissingInputException();
        $commandTester->execute(
            [
                'username' => 'daniel',
                'email' => 'daniel@silverback.is',
            ]
        );
    }

    private function expectMissingInputException()
    {
        if (class_exists(MissingInputException::class)) {
            $this->expectException(MissingInputException::class);
        } else {
            $this->expectException(RuntimeException::class);
        }
    }
}
