<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Uploads;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyImageComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithAdminWrite;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class UploadWriteSecurityTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use UploadsAreaTrait;

    public function test_an_anonymous_upload_to_a_resource_whose_patch_is_admin_only_is_refused(): void
    {
        $client = static::createClient();
        $resource = self::createUploadable(new DummyUploadableWithAdminWrite());

        $this->uploadMultipart($client, self::iriOf($resource) . '/upload', ['file' => 'image.svg']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_users_upload_to_a_resource_whose_patch_is_admin_only_is_refused_with_the_patch_message(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithAdminWrite());
        $filename = self::findByIri(self::iriOf($resource))?->getFilename();

        $this->uploadMultipart($client, self::iriOf($resource) . '/upload', ['file' => 'image.svg']);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonNodeSame('detail', 'Only an admin may write this resource.');
        self::assertSame($filename, self::findByIri(self::iriOf($resource))?->getFilename());
    }

    public function test_an_admins_upload_to_a_resource_whose_patch_is_admin_only_is_stored(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resource = self::createUploadable(new DummyUploadableWithAdminWrite());

        $this->uploadMultipart($client, self::iriOf($resource) . '/upload', ['file' => 'image.svg']);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesRegularExpression('#^image-[0-9a-f]{8}\.svg$#', (string) self::findByIri(self::iriOf($resource))?->getFilename());
    }

    public function test_a_users_upload_to_a_component_is_refused_by_the_publishable_permission(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyImageComponent());

        $this->uploadMultipart($client, self::iriOf($resource) . '/upload', ['file' => 'image.svg']);

        self::assertResponseStatusCodeSame(403);
    }

    public function test_the_download_still_follows_the_public_get(): void
    {
        $client = static::createClient();
        $resource = self::createUploadable(new DummyUploadableWithAdminWrite());

        $client->request('GET', self::iriOf($resource) . '/download/file');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type', 'image/png');
    }
}
