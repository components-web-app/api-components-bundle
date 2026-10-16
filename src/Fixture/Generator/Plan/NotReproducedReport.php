<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Plan;

use ApiPlatform\Metadata\Exception\ExceptionInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;

/**
 * @internal
 */
final class NotReproducedReport
{
    /** @var list<string|array{string, string, string}> */
    private array $messages = [];

    /** @var array<string, \SplObjectStorage<object, null>> */
    private array $entities = [];

    public function __construct(private readonly IriConverterInterface $iriConverter)
    {
    }

    public function add(string $message): void
    {
        $this->messages[] = $message;
    }

    public function addForEach(string $message, object $entity): void
    {
        $message = rtrim($message, '.');
        if (!isset($this->entities[$message])) {
            $this->entities[$message] = new \SplObjectStorage();
            $this->messages[] = match (true) {
                $entity instanceof AbstractComponent => [$message, 'component', 'components'],
                $entity instanceof AbstractPageData => [$message, 'page data', 'page data'],
                default => [$message, 'entity', 'entities'],
            };
        }
        $this->entities[$message]->offsetSet($entity);
    }

    public function describe(object $entity): string
    {
        try {
            return $this->iriConverter->getIriFromResource($entity) ?? $entity::class;
        } catch (ExceptionInterface) {
            return $entity::class;
        }
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_map(function (string|array $message): string {
            if (\is_string($message)) {
                return $message;
            }
            [$text, $singular, $plural] = $message;
            $count = \count($this->entities[$text]);

            return \sprintf('%s (%d %s).', $text, $count, 1 === $count ? $singular : $plural);
        }, $this->messages);
    }
}
