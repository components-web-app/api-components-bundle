<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Nav;

use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\FeaturesPage;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\FormPage;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\GalleryPage;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\HomePage;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\Navigation\Hero\HeroNavbarPage;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Page\NewsPage;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\DataFixtures\Nav\AbstractNav;

class LayoutNav extends AbstractNav implements DependentFixtureInterface
{
    /**
     * @param ObjectManager $manager
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager)
    {
        parent::load($manager);
        $this->getReference('layout.default')->setNav($this->entity);

        $this->addNavItem('Home', 0, $this->getReference('page.home'));
        $navs = $this->addNavItem('Navigation', 0, $this->getReference('page.navigation'));
        $this->addNavItem('Forms', 0, $this->getReference('page.forms'));
        $this->addNavItem('Features', 0, $this->getReference('page.features'));
        $this->addNavItem('Gallery', 0, $this->getReference('page.gallery'));
        $this->addNavItem('News / Blog', 0, $this->getReference('page.news'));

        $this->addReference('nav.layout', $this->entity);
        $this->addReference('nav.layout.navs', $navs);
        $this->flush();
    }

    /**
     * Return page fixtures that this nav uses
     * @return array
     */
    public function getDependencies()
    {
        return [
            HomePage::class,
            FormPage::class,
            HeroNavbarPage::class,
            FeaturesPage::class,
            GalleryPage::class,
            NewsPage::class
        ];
    }
}