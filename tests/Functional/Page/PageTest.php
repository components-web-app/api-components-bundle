<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class PageTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use ConsoleTrait;
    use JsonTrait;
    use ManifestTrait;
    use SiteTrait;

    private const string CIRCULAR_PARENT = 'Setting this parent would create a circular reference.';

    public function test_an_admin_can_create_a_page(): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();

        self::postPage($client, ['layout' => self::iriOf($layout), 'reference' => 'home', 'uiComponent' => 'myComponent', 'isTemplate' => false]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('page.schema.json');
    }

    public function test_a_page_created_with_no_title_has_no_title(): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();

        self::postPage($client, ['layout' => self::iriOf($layout), 'reference' => 'untitled', 'uiComponent' => 'myComponent', 'isTemplate' => false]);

        self::assertResponseStatusCodeSame(201);
        self::assertNull(self::lastJson()['title'] ?? null);
        self::assertNull(self::pageByReference('untitled')->getTitle());
    }

    public function test_a_non_admin_user_cannot_create_a_page(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $layout = self::createLayout();

        self::postPage($client, ['layout' => self::iriOf($layout), 'reference' => 'home', 'uiComponent' => 'myComponent', 'isTemplate' => false]);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_an_admin_can_create_a_page_with_a_parent_page(): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();
        $page = self::createPage();

        self::postPage($client, ['layout' => self::iriOf($layout), 'reference' => 'child', 'parentPage' => self::iriOf($page), 'uiComponent' => 'myComponent', 'isTemplate' => true]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('page.schema.json');
        self::assertJsonNodeSame('parentPage', self::iriOf($page));
    }

    public function test_an_admin_can_create_a_page_with_a_parent_page_data(): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();
        $pageData = self::createEmptyPageData();

        self::postPage($client, ['layout' => self::iriOf($layout), 'reference' => 'child', 'parentPageData' => self::iriOf($pageData), 'uiComponent' => 'myComponent', 'isTemplate' => true]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('page.schema.json');
        self::assertJsonNodeSame('parentPageData', self::iriOf($pageData));
    }

    public function test_i_can_patch_a_page_to_set_a_parent_page(): void
    {
        $client = self::adminClient();
        $page = self::createPage();
        $child = self::createChildPageWithLayout();

        self::requestJsonLd($client, 'PATCH', self::iriOf($child), ['parentPage' => self::iriOf($page)]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('parentPage', self::iriOf($page));
    }

    public function test_i_cannot_set_a_page_as_its_own_parent(): void
    {
        $client = self::adminClient();
        $page = self::createPage();

        self::requestJsonLd($client, 'PATCH', self::iriOf($page), ['parentPage' => self::iriOf($page)]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
        self::assertViolation('parentPage', self::CIRCULAR_PARENT);
    }

    public function test_i_cannot_create_a_circular_parent_chain_between_two_pages(): void
    {
        $client = self::adminClient();
        $page = self::createPage();
        $child = self::createPageWithParentPage($page);

        self::requestJsonLd($client, 'PATCH', self::iriOf($page), ['parentPage' => self::iriOf($child)]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
        self::assertViolation('parentPage', self::CIRCULAR_PARENT);
    }

    public function test_i_cannot_create_a_circular_parent_chain_across_mixed_page_and_page_data_parents(): void
    {
        $client = self::adminClient();
        $page = self::createPage();
        $template = self::newPage('page-data-template', true);
        $pageData = new PageData();
        $pageData->page = $template;
        $pageData->setParentPage(self::getEntityManager()->getReference(Page::class, $page->getId()));
        self::persistAndFlush($template, $pageData);

        self::requestJsonLd($client, 'PATCH', self::iriOf($page), ['parentPageData' => self::iriOf($pageData)]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
        self::assertViolation('parentPageData', self::CIRCULAR_PARENT);
    }

    public function test_an_admin_cannot_set_both_a_parent_page_and_a_parent_page_data_on_a_page(): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();
        $page = self::createPage();
        $pageData = self::createEmptyPageData();

        self::postPage($client, ['layout' => self::iriOf($layout), 'reference' => 'child', 'parentPage' => self::iriOf($page), 'parentPageData' => self::iriOf($pageData), 'uiComponent' => 'myComponent', 'isTemplate' => true]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
        self::assertViolation('', 'A page can only have one parent: either a Page or a PageData, not both.');
    }

    #[DataProvider('invalidPages')]
    public function test_the_page_resource_validates_correctly(bool $withLayout, ?string $reference, string $uiComponent, string $propertyPath, string $message): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();

        self::postPage($client, ['layout' => $withLayout ? self::iriOf($layout) : null, 'reference' => $reference, 'uiComponent' => $uiComponent, 'isTemplate' => false]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
        self::assertJsonNodeSame('violations[0].propertyPath', $propertyPath);
        self::assertJsonNodeSame('violations[0].message', $message);
    }

    /**
     * @return iterable<string, array{bool, ?string, string, string, string}>
     */
    public static function invalidPages(): iterable
    {
        yield 'no layout' => [false, 'home', 'myComponent', 'layout', 'Please specify a layout.'];
        yield 'an empty reference' => [true, '', 'myComponent', 'reference', 'Please enter a reference.'];
        yield 'a null reference' => [true, null, 'myComponent', 'reference', 'Please enter a reference.'];
        yield 'an empty uiComponent' => [true, 'home', '', 'uiComponent', 'Please specify a UI component.'];
    }

    public function test_i_can_delete_a_page(): void
    {
        $client = self::adminClient();
        $page = self::createPage();

        self::requestJsonLd($client, 'DELETE', self::iriOf($page));

        self::assertResponseStatusCodeSame(204);
    }

    public function test_i_can_get_a_resource_manifest_for_a_flat_page_data_by_uuid(): void
    {
        $client = self::adminClient();
        $created = self::createPageDataWithRoute('/my-route');

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($created['pageData']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('resource_iris', 1);
        self::assertSame(self::iriOf($created['pageData']), self::manifestRootIriAtDepth(0));
    }

    public function test_i_can_get_a_resource_manifest_for_a_nested_page_data_by_uuid(): void
    {
        $client = self::adminClient();
        $parentPage = self::newPage('parent page', true);
        $parentPageData = new PageData();
        $parentPageData->page = $parentPage;
        $parentRoute = self::newRoute('/conference')->setPageData($parentPageData);
        $childPage = self::newPage('child page', true);
        $childPageData = new PageData();
        $childPageData->page = $childPage;
        $childPageData->setParentPageData($parentPageData);
        $childRoute = self::newRoute('/conference/programme')->setPageData($childPageData);
        self::persistAndFlush($parentPage, $parentPageData, $parentRoute, $childPage, $childPageData, $childRoute);

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($childPageData));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('resource_iris', 2);
        self::assertSame(self::iriOf($childPageData), self::manifestRootIriAtDepth(1));
    }

    public function test_i_can_patch_a_page_when_component_groups_are_included_in_the_request_body(): void
    {
        $client = self::adminClient();
        $created = self::createValidPageWithComponentGroup();

        self::requestJsonLd($client, 'PATCH', self::iriOf($created['page']), \sprintf('{"componentGroups": ["%s"]}', self::iriOf($created['group'])));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_i_can_patch_a_page_when_embedded_component_groups_with_positions_are_included_in_the_request_body(): void
    {
        $client = self::adminClient();
        $created = self::createValidPageWithComponentGroup();

        self::requestJsonLd($client, 'PATCH', self::iriOf($created['page']), [
            'componentGroups' => [
                [
                    '@id' => self::iriOf($created['group']),
                    '@type' => 'ComponentGroup',
                    'componentPositions' => [['@id' => self::iriOf($created['position']), 'sortValue' => 0]],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_page_collection_includes_parent_page_when_set(): void
    {
        $client = self::adminClient();
        $page = self::createPage();
        self::createPageWithParentPage($page);

        self::requestJsonLd($client, 'GET', '/_/pages?order[reference]=asc');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('member[0].parentPage', self::iriOf($page));
    }

    public function test_the_page_resources_can_be_ordered_descending_by_reference(): void
    {
        $client = self::adminClient();
        self::createPageWith('1');
        self::createPageWith('2');

        self::requestJsonLd($client, 'GET', '/_/pages?order[reference]=desc');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 2);
        self::assertJsonNodeSame('member[0].reference', '2');
        self::assertJsonNodeSame('member[1].reference', '1');
    }

    public function test_the_page_resources_can_be_ordered_ascending_by_created_at(): void
    {
        self::assertPagesOrderedByCreatedAt('asc', 'page_1', 'page_2');
    }

    public function test_the_page_resources_can_be_ordered_descending_by_created_at(): void
    {
        self::assertPagesOrderedByCreatedAt('desc', 'page_2', 'page_1');
    }

    public function test_the_page_search_parameter_matches_the_reference(): void
    {
        $client = self::adminClient();
        self::createPageWith('primary');
        self::createPageWith('secondary');

        self::requestJsonLd($client, 'GET', '/_/pages?search=primary');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].reference', 'primary');
    }

    public function test_the_page_search_parameter_matches_part_of_the_title_ignoring_case(): void
    {
        $client = self::adminClient();
        self::createPageWith('conf', title: 'My Conference');
        self::createPageWith('contact', title: 'Contact Us');

        self::requestJsonLd($client, 'GET', '/_/pages?search=conference');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].reference', 'conf');
    }

    public function test_the_page_search_parameter_matches_the_ui_component(): void
    {
        $client = self::adminClient();
        self::createPageWith('primary', uiComponent: 'PrimaryPage');
        self::createPageWith('secondary', uiComponent: 'SecondaryPage');

        self::requestJsonLd($client, 'GET', '/_/pages?search=PrimaryPage');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].reference', 'primary');
    }

    public function test_the_page_search_parameter_matches_across_fields_with_or(): void
    {
        $client = self::adminClient();
        self::createPageWith('alpha-page', title: 'Unrelated');
        self::createPageWith('other', title: 'The Alpha Title');
        self::createPageWith('neither', title: 'Nothing');

        self::requestJsonLd($client, 'GET', '/_/pages?search=alpha');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 2);
    }

    public function test_a_per_field_search_parameter_no_longer_filters_pages(): void
    {
        $client = self::adminClient();
        self::createPageWith('primary');
        self::createPageWith('secondary');

        self::requestJsonLd($client, 'GET', '/_/pages?reference=primary');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 2);
    }

    public function test_the_page_resources_can_be_filtered_to_show_only_templates(): void
    {
        $client = self::adminClient();
        self::createPageWith('my-template', isTemplate: true);
        self::createPageWith('not-a-template');

        self::requestJsonLd($client, 'GET', '/_/pages?isTemplate=1');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].reference', 'my-template');
    }

    public function test_the_page_template_filter_accepts_the_modules_list_of_values(): void
    {
        $client = self::adminClient();
        self::createPageWith('my-template', isTemplate: true);
        self::createPageWith('not-a-template');

        self::requestJsonLd($client, 'GET', '/_/pages?isTemplate[]=true');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].reference', 'my-template');

        self::requestJsonLd($client, 'GET', '/_/pages?isTemplate[]=true&isTemplate[]=false');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 2);
    }

    public function test_an_invalid_sort_direction_is_refused(): void
    {
        $client = self::adminClient();
        self::createPageWith('primary');

        self::requestJsonLd($client, 'GET', '/_/pages?order[reference]=sideways');

        self::assertResponseStatusCodeSame(422);
    }

    public function test_i_can_get_a_resource_manifest_for_a_nested_page_by_uuid(): void
    {
        $client = self::adminClient();
        $parentPage = self::newPage('parent page', true);
        $parentRoute = self::newRoute('/conference')->setPage($parentPage);
        $childPage = self::newPage('child page', true);
        $childPage->setParentPage($parentPage);
        $childRoute = self::newRoute('/conference/programme')->setPage($childPage);
        self::persistAndFlush($parentPage, $parentRoute, $childPage, $childRoute);

        self::requestJsonLd($client, 'GET', self::manifestUrlOf($childPage));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('resource_iris', 2);
        self::assertSame(self::iriOf($childPage), self::manifestRootIriAtDepth(1));
    }

    #[DataProvider('uiComponentNames')]
    public function test_a_pages_ui_component_is_stored_as_the_full_cwa_page_component_name(string $uiComponent): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();

        self::postPage($client, ['layout' => self::iriOf($layout), 'reference' => 'posted', 'uiComponent' => $uiComponent, 'isTemplate' => false]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('uiComponent', 'CwaPagePrimaryPageTemplate');
        self::assertSame('CwaPagePrimaryPageTemplate', self::pageByReference('posted')->uiComponent);
    }

    #[DataProvider('uiComponentNames')]
    public function test_patching_a_pages_ui_component_stores_the_full_cwa_page_component_name(string $uiComponent): void
    {
        $client = self::adminClient();
        $child = self::createChildPageWithLayout();

        self::requestJsonLd($client, 'PATCH', self::iriOf($child), ['uiComponent' => $uiComponent]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('uiComponent', 'CwaPagePrimaryPageTemplate');
        self::assertSame('CwaPagePrimaryPageTemplate', self::pageByReference('child')->uiComponent);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function uiComponentNames(): iterable
    {
        yield 'the short name' => ['PrimaryPageTemplate'];
        yield 'the full name' => ['CwaPagePrimaryPageTemplate'];
    }

    public function test_the_upgrade_command_prefixes_the_ui_component_of_pages_stored_with_the_short_name(): void
    {
        self::bootKernel();
        self::createPageWith('legacy', uiComponent: 'PrimaryPageTemplate');
        self::createPageWith('current', uiComponent: 'CwaPageSecondaryPageTemplate');

        $tester = self::runCommand('silverback:api-components:prefix-page-ui-components');

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Prefixed the uiComponent of 1 page with CwaPage.', $tester->getDisplay());
        self::assertSame('CwaPagePrimaryPageTemplate', self::pageByReference('legacy')->uiComponent);
        self::assertSame('CwaPageSecondaryPageTemplate', self::pageByReference('current')->uiComponent);
    }

    public function test_a_layouts_ui_component_is_stored_as_sent(): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/layouts', <<<'JSON'
            {
              "reference": "unprefixed",
              "uiComponent": "PrimaryLayout"
            }
            JSON);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('uiComponent', 'PrimaryLayout');
    }

    #[DataProvider('overLongPageFields')]
    public function test_a_page_field_longer_than_its_column_is_a_validation_error_not_a_database_error(string $property, string $reference, string $uiComponent, string $title, string $metaDescription): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();

        self::postPage($client, ['layout' => self::iriOf($layout), 'isTemplate' => false, 'reference' => $reference, 'uiComponent' => $uiComponent, 'title' => $title, 'metaDescription' => $metaDescription]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 1);
        self::assertJsonNodeSame('violations[0].propertyPath', $property);
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function overLongPageFields(): iterable
    {
        yield 'reference' => ['reference', str_repeat('a', 256), 'PageComponent', 'Title', 'Description'];
        yield 'uiComponent' => ['uiComponent', 'page', str_repeat('a', 256), 'Title', 'Description'];
        yield 'uiComponent once prefixed' => ['uiComponent', 'page', str_repeat('a', 249), 'Title', 'Description'];
        yield 'title' => ['title', 'page', 'PageComponent', str_repeat('a', 256), 'Description'];
        yield 'metaDescription' => ['metaDescription', 'page', 'PageComponent', 'Title', str_repeat('a', 256)];
    }

    public function test_a_page_field_at_its_column_length_is_accepted(): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();

        self::postPage($client, ['layout' => self::iriOf($layout), 'isTemplate' => false, 'reference' => str_repeat('a', 255), 'uiComponent' => str_repeat('a', 248), 'title' => str_repeat('a', 255), 'metaDescription' => str_repeat('a', 255)]);

        self::assertResponseStatusCodeSame(201);
    }

    private static function assertPagesOrderedByCreatedAt(string $direction, string $first, string $second): void
    {
        $client = self::adminClient();
        self::createPageWith('page_1', createdAt: 'now');
        self::createPageWith('page_2', createdAt: '+10 seconds');

        self::requestJsonLd($client, 'GET', '/_/pages?order[createdAt]=' . $direction);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 2);
        self::assertJsonNodeSame('member[0].reference', $first);
        self::assertJsonNodeSame('member[1].reference', $second);
    }

    private static function assertViolation(string $propertyPath, string $message): void
    {
        $violations = array_map(static fn (array $violation): array => [$violation['propertyPath'], $violation['message']], self::jsonNode('violations'));

        self::assertContains([$propertyPath, $message], $violations);
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function postPage(Client $client, array $data): void
    {
        self::requestJsonLd($client, 'POST', '/_/pages', $data);
    }

    private static function createEmptyPageData(): PageDataWithComponent
    {
        $page = self::newPage('test page', true);
        $pageData = new PageDataWithComponent();
        $pageData->page = $page;
        self::persistAndFlush($page, $pageData);

        return $pageData;
    }

    private static function createChildPageWithLayout(): Page
    {
        $layout = self::createLayout();
        $page = self::newPage('child', true);
        $page->layout = self::getEntityManager()->getReference($layout::class, $layout->getId());
        $page->uiComponent = 'myComponent';
        self::persistAndFlush($page);

        return $page;
    }

    private static function createPageWithParentPage(Page $parent): Page
    {
        $page = self::newPage('child-page', true);
        $page->setParentPage(self::getEntityManager()->getReference(Page::class, $parent->getId()));
        self::persistAndFlush($page);

        return $page;
    }

    private static function createPageWith(string $reference, bool $isTemplate = false, ?string $createdAt = null, ?string $title = null, ?string $uiComponent = null): Page
    {
        $page = self::newPage($reference, $isTemplate);
        $page->setTitle($title);
        $page->uiComponent = $uiComponent;
        $manager = self::getEntityManager();
        if (null !== $createdAt) {
            $page->setCreatedAt(new \DateTimeImmutable($createdAt));
        }
        static::getContainer()->get(TimestampedDataPersister::class)->persistTimestampedFields($page, null === $createdAt);
        $manager->persist($page);
        $manager->flush();
        static::rebootKernel();

        return $page;
    }

    /**
     * @return array{page: Page, group: ComponentGroup, position: ComponentPosition}
     */
    private static function createValidPageWithComponentGroup(): array
    {
        $layout = self::newLayout();
        $page = self::newPage('page-with-group');
        $page->uiComponent = 'TestComponent';
        $page->layout = $layout;
        $created = self::newComponentGroupWithComponents(1);
        $page->addComponentGroup($created['group']);
        self::persistAndFlush($layout, $page, $created['group'], ...$created['components'], ...$created['positions']);

        return ['page' => $page, 'group' => $created['group'], 'position' => $created['positions'][0]];
    }

    private static function pageByReference(string $reference): Page
    {
        $manager = self::getEntityManager();
        $manager->clear();
        $page = $manager->getRepository(Page::class)->findOneBy(['reference' => $reference]);
        self::assertInstanceOf(Page::class, $page, \sprintf('There is no page with the reference "%s".', $reference));

        return $page;
    }
}
