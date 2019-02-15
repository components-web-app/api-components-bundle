<?php

namespace Silverback\ApiComponentBundle\Entity\Form;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class LoginForm
{
    /**
     * @var int
     */
    protected $id = 0;

    /**
     * @var string
     */
    protected $_username = '';

    /**
     * @var string
     */
    protected $_password = '';

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->_username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username): void
    {
        $this->_username = $username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->_password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password): void
    {
        $this->_password = $password;
    }
}
