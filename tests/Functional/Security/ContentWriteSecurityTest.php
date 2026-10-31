<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyHtmlContent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class ContentWriteSecurityTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use SiteTrait;

    #[DataProvider('contentWrites')]
    public function test_a_signed_in_user_without_the_publishable_permission_cannot_write_cwa_content(string $method, string $target, string $suffix = ''): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $url = self::targetUrl($target) . $suffix;

        self::requestJsonLd($client, $method, $url, []);

        self::assertResponseStatusCodeSame(403);
    }

    public static function contentWrites(): iterable
    {
        foreach (['dummy component' => 'component', 'html content' => 'htmlContent', 'page' => 'page', 'route' => 'route', 'layout' => 'layout', 'component group' => 'group', 'component position' => 'position', 'page data' => 'pageData', 'form' => 'form'] as $name => $target) {
            yield 'a POST of a ' . $name => ['POST', $target . 'Collection'];
            yield 'a PATCH of a ' . $name => ['PATCH', $target];
            yield 'a DELETE of a ' . $name => ['DELETE', $target];
        }
        yield 'a PUT of a route' => ['PUT', 'route'];
        yield 'a PUT of a form' => ['PUT', 'form'];
        yield 'a route generation' => ['POST', 'routeGenerate'];
        yield 'a component group order PATCH' => ['PATCH', 'group', '/order'];
        yield 'a component move' => ['POST', 'component', '/move'];
    }

    public function test_an_anonymous_caller_cannot_write_cwa_content(): void
    {
        $client = static::createClient();
        $site = self::createRoutedPageWithComponent('/content');

        self::requestJsonLd($client, 'PATCH', self::iriOf($site['component']), []);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_an_admin_can_create_a_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/component/dummy_html_contents', ['html' => '<p>Hello</p>']);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_an_admin_can_update_a_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = new DummyHtmlContent();
        self::persistAndFlush($component);

        self::requestJsonLd($client, 'PATCH', self::iriOf($component), ['html' => '<p>Changed</p>']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_an_admin_can_create_a_page(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layout = self::createLayout();

        self::requestJsonLd($client, 'POST', '/_/pages', ['reference' => 'new-page', 'isTemplate' => false, 'uiComponent' => 'Primary', 'layout' => self::iriOf($layout)]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_an_admin_can_reorder_a_component_group(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createComponentGroupWithComponents(2);

        self::requestJsonLd($client, 'PATCH', self::iriOf($created['group']) . '/order', ['componentPositions' => [self::iriOf($created['positions'][1]), self::iriOf($created['positions'][0])]]);

        self::assertResponseStatusCodeSame(200);
    }

    #[DataProvider('formSubmits')]
    public function test_a_signed_in_user_can_submit_a_form(string $method, int $status): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $form = new Form();
        $form->formType = TestType::class;
        self::persistAndFlush($form);

        self::requestJsonLd($client, $method, self::iriOf($form) . '/submit', ['test' => ['name' => 'John Smith', 'company' => 'Silverback']]);

        self::assertResponseStatusCodeSame($status);
    }

    #[DataProvider('formSubmits')]
    public function test_an_anonymous_caller_can_submit_a_form(string $method, int $status): void
    {
        $client = static::createClient();
        $form = new Form();
        $form->formType = TestType::class;
        self::persistAndFlush($form);

        self::requestJsonLd($client, $method, self::iriOf($form) . '/submit', ['test' => ['name' => 'John Smith', 'company' => 'Silverback']]);

        self::assertResponseStatusCodeSame($status);
    }

    public static function formSubmits(): iterable
    {
        yield 'a validating PATCH' => ['PATCH', 200];
        yield 'a POST' => ['POST', 201];
    }

    private static function targetUrl(string $target): string
    {
        return match ($target) {
            'componentCollection' => '/component/dummy_components',
            'htmlContentCollection' => '/component/dummy_html_contents',
            'pageCollection' => '/_/pages',
            'routeCollection' => '/_/routes',
            'routeGenerate' => '/_/routes/generate',
            'layoutCollection' => '/_/layouts',
            'groupCollection' => '/_/component_groups',
            'positionCollection' => '/_/component_positions',
            'pageDataCollection' => '/page_data/page_data_with_components',
            'formCollection' => '/component/forms',
            default => self::siteIri($target),
        };
    }

    private static function siteIri(string $target): string
    {
        $site = self::createRoutedPageWithComponent('/content');
        $pageData = self::createPageDataWithRoute('/page-data');
        $htmlContent = new DummyHtmlContent();
        $form = new Form();
        $form->formType = TestType::class;
        self::persistAndFlush($htmlContent, $form);

        return self::iriOf(match ($target) {
            'htmlContent' => $htmlContent,
            'pageData' => $pageData['pageData'],
            'form' => $form,
            default => $site[$target],
        });
    }
}
