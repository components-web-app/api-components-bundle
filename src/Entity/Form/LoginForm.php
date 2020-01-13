<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Form;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class LoginForm
{
    protected int $id = 0;
    public string $_username = '';
    public string $_password = '';

    public function getId(): int
    {
        return $this->id;
    }
}
