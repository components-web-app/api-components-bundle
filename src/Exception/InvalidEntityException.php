<?php

namespace Silverback\ApiComponentBundle\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

class InvalidEntityException extends \InvalidArgumentException
{
    /**
     * @var ConstraintViolationListInterface
     */
    private $errors;

    public function __construct(ConstraintViolationListInterface $errors, string $message = '', int $code = 0, Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct((string)$errors . '. ' . $message, $code, $previous);
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
