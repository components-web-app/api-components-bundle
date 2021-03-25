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

namespace Silverback\ApiComponentsBundle\RefreshToken\Storage;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\RefreshToken\RefreshToken;
use Silverback\ApiComponentsBundle\Repository\Core\RefreshTokenRepository;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class DoctrineRefreshTokenStorage implements RefreshTokenStorageInterface
{
    private ManagerRegistry $registry;
    private int $ttl;
    private string $className;

    public function __construct(ManagerRegistry $registry, int $ttl, array $options)
    {
        $this->registry = $registry;
        $this->ttl = $ttl;

        if (!isset($options['class'])) {
            throw new \InvalidArgumentException('You must specify silverback_api_components.refresh.token.options.class option.');
        }

        $this->className = $options['class'];
    }

    public function findOneByUser(UserInterface $user): ?RefreshToken
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository($this->className);
        if (!$repository instanceof RefreshTokenRepository) {
            throw new \InvalidArgumentException('RefreshToken entity repository must be instance of ' . RefreshTokenRepository::class);
        }

        return $repository->findOneByUser($user);
    }

    public function create(UserInterface $user, bool $flush = true): RefreshToken
    {
        $className = $this->className;
        /** @var RefreshToken $refreshToken */
        $refreshToken = new $className();
        $refreshToken->setCreatedAt(new \DateTimeImmutable());
        $refreshToken->setExpiresAt(new \DateTimeImmutable("$this->ttl seconds"));
        $refreshToken->setUser($user);

        $em = $this->getEntityManager();
        $em->persist($refreshToken);

        if ($flush) {
            $em->flush($refreshToken);
        }

        return $refreshToken;
    }

    // in 1 transaction so concurrent requests will not find no valid request token
    public function createAndExpire(UserInterface $user, RefreshToken $refreshToken): RefreshToken
    {
        $em = $this->getEntityManager();
        $newToken = $this->create($user, false);
        $this->expireToken($refreshToken, false);
        $em->flush();

        return $newToken;
    }

    public function createAndExpireAll(UserInterface $user): RefreshToken
    {
        $em = $this->getEntityManager();
        $newToken = $this->create($user, false);
        $this->expireAll($user, false);
        $em->flush();

        return $newToken;
    }

    public function expireAll(?UserInterface $user, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository($this->className);
        $refreshTokens = $user ? $repository->findBy(['user' => $user]) : $repository->findAll();

        foreach ($refreshTokens as $refreshToken) {
            /* @var RefreshToken $refreshToken */
            $this->expireToken($refreshToken, false);
        }
        if ($flush) {
            $em->flush();
        }
    }

    public function expireToken(RefreshToken $refreshToken, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        if (!$refreshToken->isExpired()) {
            $refreshToken->setExpiresAt(new \DateTimeImmutable());
        }
        if ($flush) {
            $em->flush();
        }
    }

    private function getEntityManager(): EntityManager
    {
        /** @var EntityManager|null $em */
        $em = $this->registry->getManagerForClass($this->className);
        if (!$em) {
            throw new EntityNotFoundException('No entity found for class RefreshToken::class.');
        }

        return $em;
    }
}
