<?php

namespace Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Gallery;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Component\Gallery\Gallery;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\AbstractComponentFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class GalleryFactory extends AbstractComponentFactory
{
    /** @var GalleryItemFactory */
    private $itemFactory;

    public function __construct(ObjectManager $manager, ValidatorInterface $validator, GalleryItemFactory $itemFactory)
    {
        $this->itemFactory = $itemFactory;
        parent::__construct($manager, $validator);
    }

    public function getItemFactory()
    {
        return $this->itemFactory;
    }

    /**
     * @inheritdoc
     */
    public function create(?array $ops = null): Gallery
    {
        $component = new Gallery();
        $this->init($component, $ops);
        $this->validate($component);
        return $component;
    }
}
