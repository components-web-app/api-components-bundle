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

namespace Silverback\ApiComponentsBundle\Command;

use Doctrine\ORM\EntityNotFoundException;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
                    new InputOption('field', null, InputOption::VALUE_REQUIRED, 'The user field (username, email)', 'username'),
                ]
            )
            ->setHelp(
                <<<EOT
                    The <info>silverback:api-components:refresh-token:expire</info> command expires all refresh-tokens or by user:
                      <info>php %command.full_name%</info>
                      <info>php %command.full_name% username</info>
                    EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($username = (string) $input->getArgument('username')) {
            $user = $this->repository->findOneBy([$input->getOption('field') => $username]);
            if (!$user) {
                throw new EntityNotFoundException(\sprintf('User with username "%s" not found.', $username));
            }
            $this->storage->expireAll($user);
            $output->writeln(\sprintf('RefreshTokens for user <comment>%s</comment> successfully expired.', $username));
        } else {
            $this->storage->expireAll(null);
            $output->writeln('RefreshTokens for all users successfully expired.');
        }

        return 0;
    }
}
