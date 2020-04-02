<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Command;

use Exception;
use Silverback\ApiComponentBundle\Factory\UserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * From FOSUserBundle: https://github.com/FriendsOfSymfony/FOSUserBundle/blob/master/Command/CreateUserCommand.php.
 */
class UserCreateCommand extends Command
{
    protected static $defaultName = 'silverback:api-component:user:create';
    private UserFactory $userFactory;

    public function __construct(UserFactory $userFactory)
    {
        parent::__construct();
        $this->userFactory = $userFactory;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a user.')
            ->setDefinition([
                new InputArgument('username', InputArgument::REQUIRED, 'The username'),
                new InputArgument('email', InputArgument::REQUIRED, 'The email'),
                new InputArgument('password', InputArgument::REQUIRED, 'The password'),
                new InputOption('super-admin', null, InputOption::VALUE_NONE, 'Set the user as super admin'),
                new InputOption('inactive', null, InputOption::VALUE_NONE, 'Set the user as inactive'),
                new InputOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite the user if they already exist'),
            ])
            ->setHelp(<<<'EOT'
                The <info>silverback:api-component:user:create</info> command creates a user:
                  <info>php %command.full_name% daniel</info>
                This interactive shell will ask you for an email and then a password.
                You can alternatively specify the email and password as the second and third arguments:
                  <info>php %command.full_name% daniel daniel@example.com mypassword</info>
                You can create a super admin via the super-admin flag:
                  <info>php %command.full_name% admin --super-admin</info>
                You can create an inactive user (will not be able to log in):
                  <info>php %command.full_name% disabled_user --inactive</info>
                You can overwrite a user if they already exist:
                  <info>php %command.full_name% existing_username --overwrite</info>
                EOT
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $inactive = $input->getOption('inactive');
        $superadmin = $input->getOption('super-admin');
        $overwrite = $input->getOption('overwrite');

        $this->userFactory->create($username, $password, $email, $inactive, $superadmin, $overwrite);

        $output->writeln(sprintf('Created user <comment>%s</comment>', $username));
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $questions = [];

        if (!$input->getArgument('username')) {
            $question = new Question('Please choose a username:');
            $question->setValidator(
                static function ($username) {
                    if (empty($username)) {
                        throw new Exception('Username can not be empty');
                    }

                    return $username;
                });
            $questions['username'] = $question;
        }

        if (!$input->getArgument('email')) {
            $question = new Question('Please choose an email (leave blank to use same as username):');
            $questions['email'] = $question;
        }

        if (!$input->getArgument('password')) {
            $question = new Question('Please choose a password:');
            $question->setValidator(
                static function ($password) {
                    if (empty($password)) {
                        throw new Exception('Password can not be empty');
                    }

                    return $password;
                });
            $question->setHidden(true);
            $questions['password'] = $question;
        }

        foreach ($questions as $name => $question) {
            $answer = $this->getHelper('question')->ask($input, $output, $question);
            $input->setArgument($name, $answer);
        }
    }
}
