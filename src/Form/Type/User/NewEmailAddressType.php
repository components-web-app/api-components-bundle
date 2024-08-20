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

namespace Silverback\ApiComponentsBundle\Form\Type\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Form\AbstractType;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class NewEmailAddressType extends AbstractType
{
    private Security $security;
    private UserRepositoryInterface $userRepository;
    private string $userClass;

    public function __construct(Security $security, UserRepositoryInterface $userRepository, string $userClass)
    {
        if (!is_subclass_of($userClass, AbstractUser::class)) {
            throw new InvalidArgumentException(\sprintf('The user class `%s` provided to the form `%s` must extend `%s`', $this->userClass, __CLASS__, AbstractUser::class));
        }
        $this->security = $security;
        $this->userRepository = $userRepository;
        $this->userClass = $userClass;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->security->getUser();
        if (!$data instanceof AbstractUser) {
            throw new InvalidArgumentException(\sprintf('The logged in user must be an instance of %s to use the form %s', AbstractUser::class, __CLASS__));
        }
        $help = null;
        if ($data instanceof AbstractUser && $data->getNewEmailAddress()) {
            $help = \sprintf('You have requested to change your email to `%s`. Please check your inbox to validate this email address.', $data->getNewEmailAddress());
        }
        $builder
            ->add(
                'newEmailAddress',
                EmailType::class,
                [
                    'label' => 'Login Email',
                    'attr' => ['autocomplete' => 'username email'],
                    'data' => $data ? $data->getEmailAddress() : '',
                    'help' => $help,
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /** @var AbstractUser $securityUser */
        $securityUser = $this->security->getUser();

        // With JWT, we are not hitting the database for the user, but in this case we should be.
        // This is so we can detect change sets in the form listeners.
        $databaseUser = $this->userRepository->find($securityUser->getId());

        $resolver->setDefaults(
            [
                'csrf_protection' => false,
                'attr' => [
                    'novalidate' => 'novalidate',
                ],
                'data_class' => $this->userClass,
                'validation_groups' => ['User:emailAddress'],
                'empty_data' => $databaseUser,
            ]
        );
    }
}
