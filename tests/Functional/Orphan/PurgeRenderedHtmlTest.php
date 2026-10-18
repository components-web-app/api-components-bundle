<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Orphan;

use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class PurgeRenderedHtmlTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use CacheTagsTrait;
    use SiteTrait;

    public function test_updating_a_site_config_parameter_purges_the_rendered_html_tag_as_well_as_its_own_iris(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $parameter = self::iriOf(self::createSiteConfigParameter());

        self::requestJsonLd($client, 'PUT', $parameter, ['key' => 'key', 'value' => 'new_value']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($parameter);
        self::assertCacheTagPurged('cwa-html');
    }

    public function test_creating_a_site_config_parameter_purges_the_rendered_html_tag(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/_/site_config_parameters', ['key' => 'siteName', 'value' => 'CWA']);

        self::assertResponseStatusCodeSame(201);
        self::assertCacheTagPurged('cwa-html');
    }

    public function test_deleting_a_site_config_parameter_purges_the_rendered_html_tag(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $parameter = self::iriOf(self::createSiteConfigParameter());

        self::requestJsonLd($client, 'DELETE', $parameter);

        self::assertResponseStatusCodeSame(204);
        self::assertCacheTagPurged('cwa-html');
    }

    public function test_a_write_to_an_unlisted_resource_class_purges_only_its_own_iris(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $layout = self::iriOf(self::createLayout());
        $group = self::iriOf(self::createComponentGroupWithComponents(0)['group']);

        self::requestJsonLd($client, 'PATCH', $group, ['layouts' => [$layout]]);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($layout);
        self::assertCacheTagNotPurged('cwa-html');
    }

    public function test_a_single_write_sends_the_rendered_html_tag_once_however_many_resources_it_invalidates(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $parameters = [];
        for ($i = 0; $i < 3; ++$i) {
            $parameters[] = (new SiteConfigParameter())->setKey(\sprintf('key_%d', $i))->setValue(\sprintf('value_%d', $i));
        }
        self::persistAndFlush(...$parameters);

        self::requestJsonLd($client, 'PUT', self::iriOf($parameters[0]), ['key' => 'key_0', 'value' => 'new_value']);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurgedTimes('cwa-html', 1);
    }
}
