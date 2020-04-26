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

namespace Silverback\ApiComponentBundle\Timestamped;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Annotation\Timestamped;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Utility\ClassMetadataTrait;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class TimestampedHelper
{
    use ClassMetadataTrait;

    private Reader $reader;
    private AuthorizationCheckerInterface $authorizationChecker;
    private string $permission;

    public function __construct(Reader $reader, ManagerRegistry $registry)
    {
        $this->reader = $reader;
        $this->initRegistry($registry);
    }

    /**
     * @param object|string $class
     */
    public function isTimestamped($class): bool
    {
        try {
            $this->getConfiguration($class);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param object|string $class
     */
    public function getConfiguration($class): Timestamped
    {
        if (null === $class || (\is_string($class) && !class_exists($class))) {
            throw new InvalidArgumentException(sprintf('$class passed to %s must be a valid class FQN or object', __CLASS__));
        }

        $reflection = new \ReflectionClass($class);
        /** @var Timestamped|null $timestamped */
        while (
            !($timestamped = $this->reader->getClassAnnotation($reflection, Timestamped::class)) &&
            ($reflection = $reflection->getParentClass())
        ) {
            continue;
        }
        if (!$timestamped) {
            throw new InvalidArgumentException(sprintf('%s does not have Publishable annotation', \is_object($class) ? \get_class($class) : $class));
        }

        return $timestamped;
    }
}
