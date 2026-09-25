<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource;

use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Ramsey\Uuid\Doctrine\UuidType;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\MappedSuperclassDiscriminatorMapListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableListener;
use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceDetector;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;

abstract class OrphanedResourceDatabaseTestCase extends TestCase
{
    protected EntityManager $entityManager;
    protected ManagerRegistry $registry;
    protected TimestampedDataPersister $timestampedDataPersister;
    protected OrphanedResourceDetector $detector;
    protected IriConverterInterface $iriConverter;
    protected PublishableAttributeReader $publishableAttributeReader;

    protected AbstractLogger $queryLogger;

    protected function setUp(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
        $registry = $this->createStub(ManagerRegistry::class);
        $configuration = ORMSetup::createAttributeMetadataConfig([
            __DIR__ . '/../../../src/Entity',
            __DIR__ . '/../../Functional/TestBundle/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $this->queryLogger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $queries = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if (isset($context['sql'])) {
                    $this->queries[] = $context['sql'];
                }
            }
        };
        $configuration->setMiddlewares([new Middleware($this->queryLogger)]);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->registry = $registry;

        $events = $this->entityManager->getEventManager();
        $events->addEventListener(Events::loadClassMetadata, new TablePrefixExtension('_acb_'));
        $events->addEventListener(Events::loadClassMetadata, new MappedSuperclassDiscriminatorMapListener());
        $events->addEventListener(Events::loadClassMetadata, new PublishableListener(new PublishableAttributeReader($registry)));
        $events->addEventListener(Events::loadClassMetadata, new UploadableListener(new UploadableAttributeReader($registry, true)));
        $events->addEventListener(Events::loadClassMetadata, new TimestampedListener(new TimestampedAttributeReader($registry)));
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->timestampedDataPersister = new TimestampedDataPersister($registry, new TimestampedAttributeReader($registry));
        $this->publishableAttributeReader = new PublishableAttributeReader($registry);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback($this->iri(...));
        $iriConverter->method('getResourceFromIri')->willReturnCallback($this->resourceFromIri(...));
        $this->iriConverter = $iriConverter;
        $this->detector = new OrphanedResourceDetector($registry, $this->publishableAttributeReader, $iriConverter);
    }

    protected function iri(object $resource): string
    {
        $metadata = $this->entityManager->getClassMetadata($resource::class);

        return \sprintf('/%s/%s', $metadata->getName(), $metadata->getIdentifierValues($resource)['id']);
    }

    protected function resourceFromIri(string $iri): object
    {
        if (preg_match('#^/([^/]+)/([^/]+)$#', $iri, $matches) && class_exists($matches[1])) {
            $resource = $this->entityManager->find($matches[1], $matches[2]);
            if ($resource) {
                return $resource;
            }
        }

        throw new ItemNotFoundException(\sprintf('Item not found for "%s".', $iri));
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    protected function persist(object $entity): object
    {
        if ($this->timestampedDataPersister->isConfigured($entity)) {
            $this->timestampedDataPersister->persistTimestampedFields($entity, true);
        }
        $this->entityManager->persist($entity);

        return $entity;
    }

    protected function group(string $reference): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $reference;

        return $this->persist($group);
    }

    protected function pageGroup(): ComponentGroup
    {
        $group = $this->group('page-group');
        $this->page()->addComponentGroup($group);

        return $group;
    }

    protected function page(): Page
    {
        $page = new Page();
        $page->reference = 'page-' . bin2hex(random_bytes(4));
        $page->isTemplate = true;

        return $this->persist($page);
    }

    protected function layout(): Layout
    {
        $layout = new Layout();
        $layout->reference = 'layout';

        return $this->persist($layout);
    }

    protected function position(ComponentGroup $group, ?AbstractComponent $component = null): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $position->sortValue = 0;
        if ($component) {
            $position->component = $this->persist($component);
        }

        return $this->persist($position);
    }
}
