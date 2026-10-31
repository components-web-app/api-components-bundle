<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Email;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Orphan\OrphanedResourcesTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\User\UserTrait;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Part\DataPart;

final class EmailLogoTest extends FunctionalTestCase
{
    use OrphanedResourcesTrait;
    use UserTrait;

    private const string CONFIGURED_LOGO = __DIR__ . '/../app/templates/emails/logo.png';

    public function test_a_user_email_shows_the_configured_logo_at_the_configured_width_with_the_site_name_as_its_alt_text(): void
    {
        $client = static::createClient();
        self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username', null, ['referer' => 'http://www.website.com']);

        self::assertResponseStatusCodeSame(200);
        self::assertShowsTheConfiguredLogo(self::assertOneTemplatedEmailSentTo('test.user@example.com'));
    }

    public function test_the_orphaned_resources_email_shows_the_configured_logo_at_the_configured_width_with_the_site_name_as_its_alt_text(): void
    {
        self::createOrphanedComponentGroup();

        $scan = self::runScheduledOrphanScan();

        self::assertCount(1, $scan['notifications']);
        self::assertShowsTheConfiguredLogo($scan['notifications'][0]);
    }

    private static function assertShowsTheConfiguredLogo(TemplatedEmail $email): void
    {
        $logos = array_values(array_filter($email->getAttachments(), static fn (DataPart $part): bool => 'logo.png' === $part->getFilename()));
        self::assertCount(1, $logos);
        $logo = $logos[0];
        self::assertSame(file_get_contents(self::CONFIGURED_LOGO), $logo->getBody());

        $document = new \DOMDocument();
        $document->loadHTML((string) $email->getHtmlBody(), \LIBXML_NOERROR);
        $images = (new \DOMXPath($document))->query(\sprintf('//img[@src="cid:%s"]', $logo->getContentId()));
        self::assertNotFalse($images);
        self::assertCount(1, $images);
        $image = $images->item(0);
        self::assertInstanceOf(\DOMElement::class, $image);
        self::assertSame('New Website', $image->getAttribute('alt'));
        self::assertSame('200', $image->getAttribute('width'));
        self::assertStringEndsWith('; width: 200px;', $image->getAttribute('style'));
    }
}
