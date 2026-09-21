<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Command;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
#[AsCommand(name: 'silverback:api-components:refresh-tokens:expire')]
final class RefreshTokensExpireCommand extends Command
{
    private const FIELDS = ['username', 'emailAddress'];

    private RefreshTokenStorageInterface $storage;
    private UserRepositoryInterface $repository;

    public function __construct(RefreshTokenStorageInterface $storage, UserRepositoryInterface $repository)
    {
        parent::__construct();
        $this->storage = $storage;
        $this->repository = $repository;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Expire all refresh-tokens or by user.')
            ->setDefinition(
                [
                    new InputArgument('username', InputArgument::OPTIONAL, 'The username'),
                    new InputOption('field', null, InputOption::VALUE_REQUIRED, \sprintf('The user field (%s)', implode(', ', self::FIELDS)), 'username'),
                ]
            )
            ->setHelp(
                <<<EOT
                    The <info>%command.name%</info> command expires all refresh-tokens or by user:
                      <info>php %command.full_name%</info>
                      <info>php %command.full_name% username</info>
                    EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $field = (string) $input->getOption('field');
        if (!\in_array($field, self::FIELDS, true)) {
            throw new InvalidOptionException(\sprintf('The field "%s" is not supported. Use one of: %s.', $field, implode(', ', self::FIELDS)));
        }

        if ($username = (string) $input->getArgument('username')) {
            $user = $this->findUser($field, $username);
            if (!$user) {
                throw new EntityNotFoundException(\sprintf('User with %s "%s" not found.', $field, $username));
            }
            $this->storage->expireAll($user);
            $output->writeln(\sprintf('RefreshTokens for user <comment>%s</comment> successfully expired.', $username));
        } else {
            $this->storage->expireAll(null);
            $output->writeln('RefreshTokens for all users successfully expired.');
        }

        return 0;
    }

    private function findUser(string $field, string $value): ?AbstractUser
    {
        if ('emailAddress' === $field) {
            return $this->repository->findOneByEmail($value);
        }

        try {
            $user = $this->repository->loadUserByIdentifier($value);
        } catch (NonUniqueResultException $exception) {
            throw new RuntimeException(\sprintf('More than one user matches "%s" as a username or email address, so the user with that username cannot be identified. No refresh-tokens were expired.', $value), 0, $exception);
        }

        if (!$user || strtolower((string) $user->getUsername()) !== strtolower($value)) {
            return null;
        }

        return $user;
    }
}
