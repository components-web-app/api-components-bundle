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

namespace Silverback\ApiComponentsBundle\Security;

use Doctrine\ORM\OptimisticLockException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidPayloadException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\UserNotFoundException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractRefreshToken;
use Silverback\ApiComponentsBundle\Event\JWTRefreshedEvent;
use Silverback\ApiComponentsBundle\RefreshToken\RefreshToken;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class JWTManager implements JWTTokenManagerInterface
{
    private JWTTokenManagerInterface $decorated;
    private EventDispatcherInterface $dispatcher;
    private UserProviderInterface $userProvider;
    private RefreshTokenStorageInterface $storage;

    public function __construct(JWTTokenManagerInterface $decorated, EventDispatcherInterface $dispatcher, UserProviderInterface $userProvider, RefreshTokenStorageInterface $storage)
    {
        $this->decorated = $decorated;
        $this->dispatcher = $dispatcher;
        $this->userProvider = $userProvider;
        $this->storage = $storage;
    }

    /**
     * {@inheritdoc}
     */
    public function create(UserInterface $user, ?AbstractRefreshToken $token = null): string
    {
        try {
            if ($token) {
                $this->storage->expireToken($token);
            } else {
                $this->storage->expireAll($user);
            }
            $this->storage->create($user);
        } catch (OptimisticLockException $exception) {
            // do nothing, we have already modified the refresh token.
            // we can continue to generate a jwt token, won't make any difference
            // if the user has a new one of these...
        }

        return $this->decorated->create($user);
    }

    /**
     * {@inheritdoc}
     */
    public function decode(TokenInterface $token)
    {
        try {
            return $this->decorated->decode($token);
        } catch (JWTDecodeFailureException $exception) {
            if (JWTDecodeFailureException::EXPIRED_TOKEN !== $exception->getReason()) {
                throw $exception;
            }

            $payload = $exception->getPayload();
            $idClaim = $this->getUserIdClaim();

            if (!isset($payload[$idClaim])) {
                throw new InvalidPayloadException($idClaim);
            }

            $identity = $payload[$idClaim];
            try {
                $user = $this->userProvider->loadUserByUsername($identity);
            } catch (UsernameNotFoundException $e) {
                throw new UserNotFoundException($idClaim, $identity);
            }

            $refreshToken = $this->resolveCurrentRefreshToken($user);
            if (!$refreshToken) {
                throw $exception;
            }

            $accessToken = $this->create($user, $refreshToken);

            $this->dispatcher->dispatch(new JWTRefreshedEvent($accessToken));

            return $this->decorated->decode(new PreAuthenticationJWTUserToken($accessToken));
        }
    }

    private function resolveCurrentRefreshToken(UserInterface $user, int $retries = 0): ?RefreshToken
    {
        // the refresh token could have just ben expired and is being refreshed by another request
        $refreshToken = $this->storage->findOneByUser($user);
        if (!$refreshToken || $refreshToken->isExpired()) {
            if ($retries < 1) {
                ++$retries;
                sleep(1);

                return $this->resolveCurrentRefreshToken($user, $retries);
            }

            return null;
        }

        return $refreshToken;
    }

    /**
     * {@inheritdoc}
     */
    public function setUserIdentityField($field)
    {
        return $this->decorated->setUserIdentityField($field);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserIdentityField(): string
    {
        return $this->decorated->getUserIdentityField();
    }

    /**
     * {@inheritdoc}
     */
    public function getUserIdClaim(): string
    {
        return $this->decorated->getUserIdClaim();
    }

    /**
     * {@inheritdoc}
     */
    public function createFromPayload(UserInterface $user, array $payload)
    {
        return $this->decorated->createFromPayload($user, $payload);
    }
}
