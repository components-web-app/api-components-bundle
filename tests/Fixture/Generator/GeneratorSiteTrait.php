<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use League\Flysystem\Filesystem;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableListener;

trait GeneratorSiteTrait
{
    private Filesystem $filesystem;

    /** @var array<string, list<object>> */
    private array $repositories = [];

    /** @var list<string> */
    private array $blankNodeClasses = [];

    private function registry(): ManagerRegistry
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $manager = $this->entityManager($registry);
        $registry->method('getManagerForClass')->willReturn($manager);
        $registry->method('getRepository')->willReturnCallback(function (string $class) {
            $repository = $this->createStub(ObjectRepository::class);
            $repository->method('findAll')->willReturn($this->repositories[$class] ?? []);

            return $repository;
        });

        return $registry;
    }

    private function entityManager(ManagerRegistry $registry): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([
            __DIR__ . '/../../../src/Entity',
            __DIR__ . '/../../Functional/TestBundle/Entity',
            __DIR__ . '/../../Command/Fixture',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        $events = $manager->getEventManager();
        $events->addEventListener(Events::loadClassMetadata, new PublishableListener(new PublishableAttributeReader($registry)));
        $events->addEventListener(Events::loadClassMetadata, new UploadableListener(new UploadableAttributeReader($registry, true)));
        $events->addEventListener(Events::loadClassMetadata, new TimestampedListener(new TimestampedAttributeReader($registry)));

        return $manager;
    }

    private function iriConverter(): IriConverterInterface
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $blankNodeClasses = $this->blankNodeClasses;
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static function (object|string $resource, int $referenceType = 0, ?Operation $operation = null) use ($blankNodeClasses): ?string {
                if (\is_string($resource) && \in_array($resource, $blankNodeClasses, true)) {
                    return '/.well-known/genid/' . md5($resource);
                }
                if (\is_string($resource)) {
                    $short = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass($resource))->getShortName()));

                    return '/component/' . $short . 's';
                }
                if ($resource instanceof Layout) {
                    return '/_/layouts/' . $resource->reference;
                }
                if ($resource instanceof Page) {
                    return '/_/pages/' . $resource->reference;
                }
                if ($resource instanceof AbstractComponent) {
                    return '/component/dummy_components/' . spl_object_id($resource);
                }

                return '/_/resources/' . spl_object_id($resource);
            }
        );

        $iriConverter->method('getResourceFromIri')->willThrowException(new InvalidArgumentException('No route matches.'));

        return $iriConverter;
    }

    private function layout(string $reference, string $uiComponent): Layout
    {
        $layout = new Layout();
        $layout->reference = $reference;
        $layout->uiComponent = $uiComponent;
        $this->repositories[Layout::class][] = $layout;

        return $layout;
    }

    private function page(string $reference, ?Layout $layout, ?Route $route = null): Page
    {
        $page = new Page();
        $page->reference = $reference;
        $page->uiComponent = 'CwaPagePrimary';
        $page->layout = $layout;
        $page->isTemplate = false;
        if (null !== $route) {
            $route->setPage($page);
        }
        $this->repositories[Page::class][] = $page;

        return $page;
    }

    private function route(string $path, string $name): Route
    {
        $route = new Route();
        $route->setPath($path);
        $route->setName($name);
        $route->setLiveAt(new \DateTimeImmutable('2000-01-01'));

        return $route;
    }

    private function group(string $reference, string $location, Layout|Page $owner): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $location;
        $owner->getComponentGroups()->add($group);
        $owner instanceof Layout ? $group->layouts->add($owner) : $group->pages->add($owner);

        return $group;
    }

    private function position(ComponentGroup $group, AbstractComponent $component, int $sortValue): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->sortValue = $sortValue;
        $position->component = $component;
        $position->componentGroup = $group;
        $group->componentPositions->add($position);

        return $position;
    }
}
