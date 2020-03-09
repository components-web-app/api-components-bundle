<?php

namespace Silverback\ApiComponentBundle\Doctrine\Extension;

use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Collection\Collection;
use Silverback\ApiComponentBundle\Entity\Component\Content\Content;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumnsItem;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStacked;
use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStackedItem;
use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList;
use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextListItem;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery;
use Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem;
use Silverback\ApiComponentBundle\Entity\Component\Hero\Hero;
use Silverback\ApiComponentBundle\Entity\Component\Image\SimpleImage;
use Silverback\ApiComponentBundle\Entity\Component\Layout\SideColumn;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu\Menu;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu\MenuItem;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar\NavBar;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar\NavBarItem;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\TabsItem;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class DiscriminatorMappingExtension
{
    private static $mappingKeys = [
        'content' => Content::class,
        'form' => Form::class,
        'gallery' => Gallery::class,
        'gallery_item' => GalleryItem::class,
        'hero' => Hero::class,
        'feature_columns' => FeatureColumns::class,
        'feature_columns_item' => FeatureColumnsItem::class,
        'feature_stacked' => FeatureStacked::class,
        'feature_stacked_item' => FeatureStackedItem::class,
        'feature_text_list' => FeatureTextList::class,
        'feature_text_list_item' => FeatureTextListItem::class,
        'nav_bar' => NavBar::class,
        'nav_bar_item' => NavBarItem::class,
        'tabs' => Tabs::class,
        'tabs_item' => TabsItem::class,
        'menu' => Menu::class,
        'menu_item' => MenuItem::class,
        'collection' => Collection::class,
        'simple_image' => SimpleImage::class,
        'layout_side_column' => SideColumn::class
    ];

    /**
     * @var MappingDriver
     */
    private $driver;

    /**
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->driver = $em->getConfiguration()->getMetadataDriverImpl();
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $reflection = $classMetadata->getReflectionClass();
        if (!$reflection) {
            return;
        }
        $reflectionName = $reflection->getName();
        if ($reflectionName !== AbstractComponent::class && $reflectionName !== DynamicContent::class) {
            return;
        }

        $this->addDiscriminatorMap($classMetadata);
    }

    protected function addDiscriminatorMap(ClassMetadata $classMetadata): void
    {
        if ($classMetadata->isRootEntity() && ! $classMetadata->isInheritanceTypeNone()) {
            $this->addDefaultDiscriminatorMap($classMetadata);
        }
    }

    /**
     * Adds a default discriminator map if no one is given
     *
     * If an entity is of any inheritance type and does not contain a
     * discriminator map, then the map is generated automatically. This process
     * is usually expensive computation wise, however by using caching, the only
     * part which is always called checks whether the class names have changed. If not,
     * no additional computation is required.
     *
     * The automatically generated discriminator map contains the lowercase short name of
     * each class as key.
     *
     * @param ClassMetadata $class
     */
    private function addDefaultDiscriminatorMap(ClassMetadata $class): void
    {
        $cache = new FilesystemAdapter();
        $allClasses = $this->driver->getAllClassNames();
        $className = $this->getShortName($class->name);
        $cachedClasses = $cache->getItem(sprintf('silverback_api_component.doctrine.driver_classes.%s', $className));
        $cachedMap = $cache->getItem(sprintf('silverback_api_component.doctrine.components_discriminator_map.%s', $className));
        if (!$cachedClasses->isHit() || $cachedClasses->get() !== $allClasses) {
            $cachedClasses->set($allClasses);
            $cache->save($cachedClasses);

            $cachedMap->set($this->getDiscriminatorMap($class, $allClasses));
            $cache->save($cachedMap);
        }
        $map = $cachedMap->get();
        $class->setDiscriminatorMap($map);
    }

    /**
     * @param ClassMetadata $class
     * @param array $allClasses
     * @return array
     * @throws MappingException
     */
    private function getDiscriminatorMap(ClassMetadata $class, array $allClasses): array
    {
        $fqcn = $class->getName();
        $map = [$this->getShortName($class->name) => $fqcn];

        $duplicates = [];
        foreach ($allClasses as $subClassCandidate) {
            if (is_subclass_of($subClassCandidate, $fqcn)) {
                $shortName = $this->getShortName($subClassCandidate);

                if (isset($map[$shortName])) {
                    $duplicates[] = $shortName;
                }

                $map[$shortName] = $subClassCandidate;
            }
        }
        if ($duplicates) {
            throw MappingException::duplicateDiscriminatorEntry($class->name, $duplicates, $map);
        }
        return $map;
    }


    /**
     * Gets the lower-case short name of a class.
     *
     * @param string $className
     *
     * @return string
     */
    private function getShortName($className): string
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();
        $name = array_search($className, self::$mappingKeys, true);
        if (!$name) {
            $parts = explode("\\", $className);
            $lastPart = end($parts);
            $name = $nameConverter->normalize($lastPart);
        }
        return $name;
    }
}
