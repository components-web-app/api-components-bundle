<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionIriResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

trait ComponentPositionDatabaseTrait
{
    use PublishableDatabaseTrait;

    /** @var array<string, object> */
    private array $iris = [];

    private function iriConverter(): IriConverterInterface
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willReturnCallback(fn (string $iri): object => $this->iris[$iri] ?? (str_starts_with($iri, '/position/') ? throw new ItemNotFoundException('Item not found') : throw new InvalidArgumentException('No route matches')));
        $iriConverter->method('getIriFromResource')->willReturnCallback(function (object|string $resource): string {
            $iri = array_search($resource, $this->iris, true);

            return false === $iri ? '/component/dummy_components' : $iri;
        });

        return $iriConverter;
    }

    private function resolver(): ComponentPositionIriResolver
    {
        return new ComponentPositionIriResolver($this->iriConverter());
    }

    /**
     * @param array<string, int> $sortValues
     */
    private function group(string $reference, array $sortValues): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $reference;
        $group->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($group);
        $this->iris['/group/' . $reference] = $group;
        foreach ($sortValues as $name => $sortValue) {
            $this->iris['/position/' . $name] = $this->position($group, $sortValue);
        }
        $this->entityManager->flush();

        return $group;
    }

    private function position(ComponentGroup $group, ?int $sortValue): ComponentPosition
    {
        $component = new DummyComponent();
        $this->entityManager->persist($component);
        $position = new ComponentPosition();
        $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $position->componentGroup = $group;
        $position->component = $component;
        $position->sortValue = $sortValue;
        $group->addComponentPosition($position);
        $this->entityManager->persist($position);

        return $position;
    }

    private function stored(ComponentGroup $group): string
    {
        $this->entityManager->clear();
        $names = [];
        foreach ($this->iris as $iri => $resource) {
            if ($resource instanceof ComponentPosition) {
                $names[(string) $resource->getId()] = substr($iri, \strlen('/position/'));
            }
        }
        $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT id, sort_value FROM _acb_component_position WHERE component_group_id = ? ORDER BY sort_value, id', [(string) $group->getId()]);

        return implode(' ', array_map(static fn (array $row) => ($names[$row['id']] ?? 'new') . $row['sort_value'], $rows));
    }
}
