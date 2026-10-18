<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Security\TokenGenerator;

class TokenGeneratorTest extends TestCase
{
    public function test_a_token_is_the_hex_of_the_requested_number_of_bytes(): void
    {
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{200}\z/', TokenGenerator::generateToken(100));
    }

    public function test_a_token_is_16_bytes_by_default(): void
    {
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', TokenGenerator::generateToken());
    }

    public function test_each_token_is_random(): void
    {
        $this->assertNotSame(TokenGenerator::generateToken(), TokenGenerator::generateToken());
    }
}
