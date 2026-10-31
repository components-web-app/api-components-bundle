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

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class ApiRequestTraitTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;

    public function test_a_post_sends_and_accepts_json_ld(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        $response = self::requestJsonLd($client, 'POST', '/component/dummy_components', ['uiComponent' => 'AnotherComponent']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('application/ld+json', $client->getKernelBrowser()->getRequest()->headers->get('content-type'));
        self::assertSame('application/ld+json', $client->getKernelBrowser()->getRequest()->headers->get('accept'));
        self::assertSame('AnotherComponent', $response->toArray()['uiComponent']);
    }

    public function test_a_patch_is_sent_as_a_merge_patch(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::requestJsonLd($client, 'POST', '/component/dummy_components', ['uiComponent' => 'Before'])->toArray()['@id'];

        $response = self::requestJsonLd($client, 'PATCH', $iri, ['uiComponent' => 'After']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('application/merge-patch+json', $client->getKernelBrowser()->getRequest()->headers->get('content-type'));
        self::assertSame('After', $response->toArray()['uiComponent']);
    }

    public function test_a_string_body_is_sent_as_it_is(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/component/dummy_components', '{"uiComponent": ');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('{"uiComponent": ', $client->getKernelBrowser()->getRequest()->getContent());
    }

    public function test_headers_given_replace_the_defaults_whatever_their_case(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/_/routes', null, ['Accept' => 'application/json', 'X-Custom' => 'value']);

        self::assertResponseStatusCodeSame(406);
        self::assertSame('application/json', $client->getKernelBrowser()->getRequest()->headers->get('accept'));
        self::assertSame('value', $client->getKernelBrowser()->getRequest()->headers->get('x-custom'));
        self::assertSame('', $client->getKernelBrowser()->getRequest()->getContent());
    }
}
