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

namespace Silverback\ApiComponentBundle\Form\Type;

use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Exception\InvalidParameterException;
use Silverback\ApiComponentBundle\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class NewEmailAddressType extends AbstractType
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $this->security->getUser();
        if (!$data instanceof AbstractUser) {
            throw new InvalidParameterException(sprintf('The logged in user must be an instance of %s to use the form %s', AbstractUser::class, __CLASS__));
        }
        $help = null;
        if ($data instanceof AbstractUser && $data->getNewEmailAddress()) {
            $help = sprintf('You have requested to change your email to `%s`. Please check your inbox to validate this email address.', $data->getNewEmailAddress());
        }
        $builder
            ->add('newEmailAddress', EmailType::class, [
                'label' => 'Login Email',
                'attr' => ['autocomplete' => 'username email'],
                'data' => $data ? $data->getEmailAddress() : null,
                'help' => $help,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
            'data_class' => AbstractUser::class,
            'validation_groups' => ['new_email_address'],
            'empty_data' => $this->security->getUser(),
        ]);
    }
}
