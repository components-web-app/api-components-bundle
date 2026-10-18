<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Security\Voter;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Route\RouteReachabilityResolver;
use Silverback\ApiComponentsBundle\Helper\SubRequest\SubRequestKernel;
use Silverback\ApiComponentsBundle\Security\Voter\ComponentVoter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ComponentVoterTest extends TestCase
{
    public function test_a_component_on_a_routed_page_is_granted_whichever_other_pages_it_is_also_on(): void
    {
        $routedPage = $this->page(new Route());
        $routelessPage = $this->page(null);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->component([$routedPage], [$routelessPage]), $routedPage));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->component([$routelessPage], [$routedPage]), $routedPage));
    }

    public function test_a_component_only_on_routeless_pages_is_denied(): void
    {
        $routedPage = $this->page(new Route());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->component([$this->page(null)], [$this->page(null)]), $routedPage));
    }

    private function vote(DummyComponent $component, Page $routedPage): int
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (string $attribute, mixed $subject) => $subject === $routedPage->getRoute());
        $pageDataProvider = $this->createStub(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturn([]);
        $pageDataProvider->method('findPageDataResourcesByPages')->willReturn([]);
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturn(false);
        $publishableStatusChecker = $this->createStub(PublishableStatusChecker::class);
        $publishableStatusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/component/dummy_components/1'));

        $voter = new ComponentVoter(
            $pageDataProvider,
            $this->createStub(IriConverterInterface::class),
            new SubRequestKernel($this->createStub(HttpKernelInterface::class)),
            $requestStack,
            $publishableStatusChecker,
            $this->createStub(ManagerRegistry::class),
            new RouteReachabilityResolver($this->createStub(ManagerRegistry::class), $security),
            $accessChecker,
            "is_granted('ROLE_ADMIN')",
        );

        return $voter->vote(new NullToken(), $component, [ComponentVoter::READ_COMPONENT]);
    }

    /**
     * @param list<Page> ...$pagesPerGroup
     */
    private function component(array ...$pagesPerGroup): DummyComponent
    {
        $component = new DummyComponent();
        foreach ($pagesPerGroup as $pages) {
            $group = new ComponentGroup();
            $group->pages = new ArrayCollection($pages);
            $position = new ComponentPosition();
            $position->componentGroup = $group;
            $component->addComponentPosition($position);
        }

        return $component;
    }

    private function page(?Route $route): Page
    {
        $page = new Page();
        if ($route) {
            $page->setRoute($route);
        }

        return $page;
    }
}
