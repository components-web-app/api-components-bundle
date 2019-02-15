<?php

namespace Silverback\ApiComponentBundle\Form\Handler;

use Silverback\ApiComponentBundle\Entity\User\User;
use App\Security\PasswordManager;
use App\Security\TokenAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Exception\UnsupportedFormEntityException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UserHandler implements FormHandlerInterface
{
    private $authenticationSuccessHandler;
    private $passwordManager;
    private $tokenAuthenticator;

    public function __construct(
        AuthenticationSuccessHandler $authenticationSuccessHandler,
        PasswordManager $passwordManager,
        TokenAuthenticator $tokenAuthenticator
    ) {
        $this->authenticationSuccessHandler = $authenticationSuccessHandler;
        $this->passwordManager = $passwordManager;
        $this->tokenAuthenticator = $tokenAuthenticator;
    }

    /**
     * @param Form $form
     * @param User $user
     * @param Request $request
     * @return null|Response
     * @throws UnsupportedFormEntityException
     */
    public function success(Form $form, $user, Request $request): ?Response
    {
        $credentials = $this->tokenAuthenticator->getCredentials($request);
        $appServerTokenUser = $this->tokenAuthenticator->getUser($credentials);
        if (!$appServerTokenUser) {
            $exception = new AuthenticationException('This form can only be submitted from the app server.');
            return $this->tokenAuthenticator->onAuthenticationFailure($request, $exception);
        }

        if (!$user instanceof User) {
            throw new UnsupportedFormEntityException(
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
        return $this->authenticationSuccessHandler->handleAuthenticationSuccess($user);
    }
}
