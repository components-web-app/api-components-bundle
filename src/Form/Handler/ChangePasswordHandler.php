<?php

namespace Silverback\ApiComponentBundle\Form\Handler;

use Silverback\ApiComponentBundle\Entity\User\User;
use App\Exception\NotSupportedException;
use App\Security\PasswordManager;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class ChangePasswordHandler implements FormHandlerInterface
{
    private $passwordManager;

    public function __construct(
        PasswordManager $passwordManager
    ) {
        $this->passwordManager = $passwordManager;
    }

    /**
     * @param Form $form
     * @param User $user
     * @param Request $request
     * @throws NotSupportedException
     */
    public function success(Form $form, $user, Request $request): void
    {
        if (!$user instanceof User) {
            throw new NotSupportedException(
                sprintf(
                    '`%s` only supports forms that submit the `%s` entity. Received `%s`',
                    self::class,
                    User::class,
                    \is_object($user) ? \get_class($user) : $user
                )
            );
        }
        $this->passwordManager->persistPlainPassword($user);
        $user->eraseCredentials();
    }
}
