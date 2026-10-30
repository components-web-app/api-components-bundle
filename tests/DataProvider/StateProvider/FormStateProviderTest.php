<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProvider\StateProvider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\FormStateProvider;

class FormStateProviderTest extends TestCase
{
    public function test_an_identifier_that_is_not_a_uuid_or_password_reset_is_not_found(): void
    {
        $default = $this->createMock(ProviderInterface::class);
        $default->expects(self::never())->method('provide');

        self::assertNull((new FormStateProvider($default))->provide(new Get(), ['id' => 'not-a-uuid']));
    }
}
