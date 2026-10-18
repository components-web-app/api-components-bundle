<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use PHPUnit\Framework\AssertionFailedError;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class ManifestTraitTest extends FunctionalTestCase
{
    use ManifestTrait;
    use SiteTrait;

    private const array MANIFEST = ['resource_iris' => [
        ['iri' => '/_/routes//a', 'children' => [['iri' => '/_/pages/1', 'children' => [['iri' => '/_/component_groups/1']]], 'not a node']],
        ['iri' => '/page_data/x/1'],
    ]];

    public function test_the_iris_of_a_depth_are_read_depth_first(): void
    {
        self::assertSame(['/_/routes//a', '/_/pages/1', '/_/component_groups/1'], self::manifestIrisAtDepth(0, self::MANIFEST));
        self::assertSame('/_/routes//a', self::manifestRootIriAtDepth(0, self::MANIFEST));
        self::assertSame('/page_data/x/1', self::manifestRootIriAtDepth(1, self::MANIFEST));
        self::assertManifestDepthContains(0, '/_/component_groups/1', self::MANIFEST);
        self::assertManifestDepthNotContains(1, '/_/pages/1', self::MANIFEST);
        self::assertManifestDepthContainsIriMatching(0, '/component_groups/', self::MANIFEST);
        self::assertManifestDepthContainsNoIriMatching(1, '/component_groups/', self::MANIFEST);
    }

    public function test_a_depth_the_manifest_does_not_have_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The manifest has no depth 2.');

        self::manifestIrisAtDepth(2, self::MANIFEST);
    }

    public function test_the_manifest_of_a_routed_page_lists_its_resources_and_each_resolves(): void
    {
        $client = static::createClient();
        $created = self::createRoutedPageWithComponent('/manifest');

        $client->request('GET', self::manifestUrlOf($created['page']), ['headers' => ['accept' => 'application/ld+json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('/_/resource_manifest/' . $created['page']->getId(), self::manifestUrlOf($created['page']));
        self::assertManifestDepthContains(0, self::iriOf($created['component']));
        self::assertManifestDepthContains(0, self::iriOf($created['position']));
        self::assertNotNull(self::manifestRootIriAtDepth(0));
        self::assertEveryManifestIriResolves();
    }

    public function test_a_manifest_iri_that_does_not_resolve_anonymously_fails(): void
    {
        static::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('These manifest IRIs did not resolve anonymously.');

        self::assertEveryManifestIriResolves(['resource_iris' => [['iri' => '/_/pages/0190c1b6-0000-7000-8000-000000000000']]]);
    }
}
