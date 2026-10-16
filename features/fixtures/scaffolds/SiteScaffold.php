<?php

namespace App\DataFixtures;

use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyHtmlContent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Symfony\Component\HttpFoundation\File\File;

class SiteScaffold extends AbstractCwaScaffold
{
    private DummyNavigationLink $contactUsDummyNavigationLink;

    public function build(CwaFixtureBuilder $cwa): void
    {
        $this->mainLayout($cwa);
        $this->homePage($cwa);
        $this->aboutPage($cwa);
        $this->eventsPage($cwa);
        $this->conferencePage($cwa);
        $this->cwaConferencePageData($cwa);
        $this->redirects($cwa);
    }

    private function mainLayout(CwaFixtureBuilder $cwa): void
    {
        $homeDummyNavigationLink = new DummyNavigationLink();
        $homeDummyNavigationLink->label = 'Home';
        $cwa->component($homeDummyNavigationLink)->linkRoute('route', routeName: 'home');

        $aboutDummyNavigationLink = new DummyNavigationLink();
        $aboutDummyNavigationLink->label = 'About';
        $cwa->component($aboutDummyNavigationLink)->linkRoute('route', routeName: 'about');

        $ourTeamDummyNavigationLink = new DummyNavigationLink();
        $ourTeamDummyNavigationLink->theme = 'subtle';
        $ourTeamDummyNavigationLink->label = 'Our team';
        $cwa->component($ourTeamDummyNavigationLink)->linkRoute('route', routeName: 'team');

        $conferenceDummyNavigationLink = new DummyNavigationLink();
        $conferenceDummyNavigationLink->label = 'Conference';
        $conferenceDummyNavigationLink->eventDate = new \DateTimeImmutable('2099-03-01T09:00:00+00:00');
        $cwa->component($conferenceDummyNavigationLink)->linkRoute('route', routeName: 'cwa-conference');

        $this->contactUsDummyNavigationLink = new DummyNavigationLink();
        $this->contactUsDummyNavigationLink->label = 'Contact us';
        $cwa->component($this->contactUsDummyNavigationLink)->linkRoute('route', routeName: 'about');

        $cwa->layout('main', uiComponent: 'Primary')
            ->group('header', allow: [DummyNavigationLink::class], max: 5)
            ->add($homeDummyNavigationLink)
            ->add($aboutDummyNavigationLink)
            ->add($ourTeamDummyNavigationLink)
            ->add($conferenceDummyNavigationLink)
            ->add($this->contactUsDummyNavigationLink);
    }

    private function homePage(CwaFixtureBuilder $cwa): void
    {
        $homeHeroDummyUploadableAndPublishable = new DummyUploadableAndPublishable();
        $homeHeroDummyUploadableAndPublishable->file = new File(__DIR__ . '/assets/home-hero.png');
        $cwa->publish($homeHeroDummyUploadableAndPublishable);

        $welcomeCwaDummyPublishableComponent = new DummyPublishableComponent();
        $welcomeCwaDummyPublishableComponent->reference = 'Welcome to CWA';
        $cwa->publish($welcomeCwaDummyPublishableComponent);

        $welcomeCwaDummyPublishableComponentDraft = new DummyPublishableComponent();
        $welcomeCwaDummyPublishableComponentDraft->reference = 'Welcome to CWA 2.0';
        $cwa->component($welcomeCwaDummyPublishableComponent)->draft($welcomeCwaDummyPublishableComponentDraft);

        $buildPagesFromComponentsDummyHtmlContent = new DummyHtmlContent();
        $buildPagesFromComponentsDummyHtmlContent->html = '<h2>Build pages from components</h2>'
            . '<p>CWA gives every page a layout, groups and positions, so editors can rearrange content without a deploy.</p>'
            . '<p>Each component is fetched and cached on its own, which keeps a content edit from purging every page that shows it.</p>'
            . '<ul><li>Layouts and pages</li><li>Reusable components</li><li>Scheduled publishing</li></ul>';

        $gettingStartedDummyHtmlContent = new DummyHtmlContent();
        $gettingStartedDummyHtmlContent->html = <<<'HTML'
            <h3>Getting started</h3><p>Install the bundle, then generate a scaffold from your site:</p><pre><code>composer require silverback/api-components-bundle
            php bin/console silverback:api-components:generate-fixtures --output=src/DataFixtures/SiteScaffold.php</code></pre>
            HTML;

        $ticketsOnSaleDummyPublishableComponent = new DummyPublishableComponent();
        $ticketsOnSaleDummyPublishableComponent->reference = 'Tickets on sale';
        $cwa->publish($ticketsOnSaleDummyPublishableComponent, at: new \DateTimeImmutable('2099-01-15T09:00:00+00:00'));

        $page = $cwa->page('home', uiComponent: 'Primary', layout: 'main', route: '/', routeName: 'home')
            ->title('Home')
            ->metaDescription('Component-driven sites with CWA');

        $page->group('hero')
            ->add($homeHeroDummyUploadableAndPublishable);

        $page->group('primary')
            ->add($welcomeCwaDummyPublishableComponent)
            ->add($buildPagesFromComponentsDummyHtmlContent)
            ->add($gettingStartedDummyHtmlContent)
            ->add($ticketsOnSaleDummyPublishableComponent)
            ->add(new DummyComponent());
    }

    private function aboutPage(CwaFixtureBuilder $cwa): void
    {
        $aboutPrimaryDummyHtmlContent = new DummyHtmlContent();
        $aboutPrimaryDummyHtmlContent->html = '<p>We started CWA to stop rebuilding the same CMS for every client.</p>'
            . '<p>It is open source and maintained by Silverback.</p>';

        $page = $cwa->page('about', uiComponent: 'Primary', layout: 'main', route: '/about')
            ->title('About')
            ->metaDescription('Who builds CWA');

        $page->group('primary')
            ->add($aboutPrimaryDummyHtmlContent);

        $page->nested($this->aboutTeamPage(...));
    }

    private function aboutTeamPage(CwaFixtureBuilder $cwa): void
    {
        $aboutTeamPrimaryDummyHtmlContent = new DummyHtmlContent();
        $aboutTeamPrimaryDummyHtmlContent->html = '<p>A small team of developers and designers.</p>';

        $cwa->page('about-team', uiComponent: 'Primary', layout: 'main', route: '/about/team', routeName: 'team')
            ->title('Team')
            ->group('primary')
            ->add($aboutTeamPrimaryDummyHtmlContent)
            ->add($this->contactUsDummyNavigationLink);
    }

    private function eventsPage(CwaFixtureBuilder $cwa): void
    {
        $cwa->page('events', uiComponent: 'Primary', layout: 'main', route: '/events')
            ->title('Events')
            ->redirectTo(routeName: 'cwa-conference');
    }

    private function conferencePage(CwaFixtureBuilder $cwa): void
    {
        $cwa->page('conference', uiComponent: 'Primary', layout: 'main', isTemplate: true)
            ->title('Conference')
            ->group('primary', allow: [DummyComponent::class])
            ->pageDataPosition(PageDataWithComponent::class, 'component');
    }

    private function cwaConferencePageData(CwaFixtureBuilder $cwa): void
    {
        $pageData = new PageDataWithComponent();
        $pageData->setTitle('CWA Conference');
        $pageData->setMetaDescription('Two days of components in London');
        $pageData->component = new DummyComponent();

        $cwa->pageData($pageData, template: 'conference', route: '/cwa-conference')
            ->liveAt(new \DateTimeImmutable('2099-01-15T09:00:00+00:00'));
    }

    private function redirects(CwaFixtureBuilder $cwa): void
    {
        $cwa->redirect('/conference', to: 'cwa-conference');
        $cwa->redirect('/our-team', to: 'team');
    }
}
