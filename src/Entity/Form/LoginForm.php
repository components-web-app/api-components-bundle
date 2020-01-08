<?php

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
