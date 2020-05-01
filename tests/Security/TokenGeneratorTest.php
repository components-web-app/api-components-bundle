<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Security {
    function random_bytes(int $bytes = 0)
    {
        return (string) $bytes;
    }

    function bin2hex($str)
    {
        return $str . '_bin2hex';
    }
}

namespace Silverback\ApiComponentsBundle\Tests\Security {
    use PHPUnit\Framework\TestCase;
    use Silverback\ApiComponentsBundle\Security\TokenGenerator;

    class TokenGeneratorTest extends TestCase
    {
        public function test_clean_token_generated(): void
        {
            $tokenGenerator = new TokenGenerator();
            $this->assertEquals('100_bin2hex', $tokenGenerator->generateToken(100));
            $this->assertEquals('16_bin2hex', $tokenGenerator->generateToken());
        }
    }
}
