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

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddress;

class NewEmailAddressTest extends TestCase
{
    public function test_new_email_address_is_class_constraint(): void
    {
        $newEmailAddressConstraint = new NewEmailAddress();
        $this->assertEquals($newEmailAddressConstraint::CLASS_CONSTRAINT, $newEmailAddressConstraint->getTargets());
    }
}
