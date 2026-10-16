<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Plan;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ScaffoldPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\EntityStateReader;
use Silverback\ApiComponentsBundle\Fixture\Generator\StoredFileExporter;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Tests\Fixture\Generator\GeneratorSiteTrait;
use Symfony\Component\DependencyInjection\ServiceLocator;

trait PlannerTestTrait
{
    use GeneratorSiteTrait;

    private function stateReader(ManagerRegistry $registry): EntityStateReader
    {
        return new EntityStateReader($registry, new UploadableAttributeReader($registry, true), new PublishableAttributeReader($registry), new TimestampedAttributeReader($registry));
    }

    private function storedFileExporter(): StoredFileExporter
    {
        return new StoredFileExporter(new FilesystemProvider(new ServiceLocator(['local' => fn () => $this->filesystem])), sys_get_temp_dir() . '/cwa-planner-test-assets');
    }

    /**
     * @param list<MethodPlan> $methods
     *
     * @return array<string, MethodPlan>
     */
    private function methodsByName(array $methods): array
    {
        $byName = [];
        foreach (ScaffoldPlan::inFileOrder($methods) as $method) {
            $byName[$method->name] = $method;
        }

        return $byName;
    }

    /**
     * @param list<ComponentPlan> $plans
     *
     * @return list<string>
     */
    private function names(array $plans): array
    {
        return array_map(static fn (ComponentPlan $plan): string => $plan->name, $plans);
    }

    private function ownGroup(AbstractComponent $owner, string $name): ComponentGroup
    {
        $iri = '/component/dummy_components/' . spl_object_id($owner);
        $group = new ComponentGroup();
        $group->reference = $name . '_' . $iri;
        $group->location = $iri;
        $owner->addComponentGroup($group);
        $group->components->add($owner);

        return $group;
    }
}
