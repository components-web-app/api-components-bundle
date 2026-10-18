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

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;

final class ExplicitAllowOnlyTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use JsonTrait;

    public function test_the_api_docs_flag_explicit_allow_only_component_types_on_their_supported_class_entry(): void
    {
        self::requestJsonLd(static::createClient(), 'GET', '/docs');

        self::assertResponseStatusCodeSame(200);
        self::assertTrue(self::explicitAllowOnlyOf('RestrictedComponent'));
        self::assertFalse(self::explicitAllowOnlyOf('DummyComponent'));
    }

    private static function explicitAllowOnlyOf(string $class): bool
    {
        $json = self::lastJson();
        foreach ($json['supportedClass'] ?? $json['hydra:supportedClass'] ?? [] as $supportedClass) {
            if (($supportedClass['title'] ?? null) === $class) {
                return true === ($supportedClass['explicitAllowOnly'] ?? false);
            }
        }

        self::fail(\sprintf('supportedClass "%s" not found in the API docs.', $class));
    }
}
