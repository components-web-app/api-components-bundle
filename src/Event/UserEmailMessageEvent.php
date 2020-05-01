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

namespace Silverback\ApiComponentsBundle\Event;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserEmailMessageEvent extends Event
{
    private string $factoryClass;
    private TemplatedEmail $email;

    public function __construct(string $factoryClass, TemplatedEmail $email)
    {
        $this->factoryClass = $factoryClass;
        $this->email = $email;
    }

    public function getFactoryClass(): string
    {
        return $this->factoryClass;
    }

    public function setEmail(TemplatedEmail $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): TemplatedEmail
    {
        return $this->email;
    }
}
