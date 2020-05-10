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

namespace Silverback\ApiComponentsBundle\Tests\Factory\User\Mailer;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TestEmailCase extends TestCase
{
    protected function assertEmailEquals(TemplatedEmail $expected, TemplatedEmail $generatedEmail, string $prefix): void
    {
        $generatedHeaders = $generatedEmail->getHeaders();
        $messageId = $generatedHeaders->get('x-message-id')->getBodyAsString();
        $generatedHeaders->remove('x-message-id');
        $this->assertEquals($expected, $generatedEmail);
        $this->assertStringStartsWith($prefix, $messageId);
    }
}
