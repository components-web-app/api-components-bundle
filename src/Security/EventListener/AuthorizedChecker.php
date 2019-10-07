<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Security\EventListener;

use Silverback\ApiComponentBundle\Entity\User\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AuthorizedChecker
{
    private $tokenStorage;
    private $authorizationChecker;

    public function __construct(TokenStorageInterface $tokenStorage, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function isAuthorized($isGrantedStr = 'ROLE_ADMIN'): bool
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return false;
        }
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        return $this->authorizationChecker->isGranted($isGrantedStr);
    }

    public function getAuthorizationChecker(): AuthorizationCheckerInterface
    {
        return $this->authorizationChecker;
    }
}
