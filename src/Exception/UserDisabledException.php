<?php

namespace Silverback\ApiComponentBundle\Exception;

use Symfony\Component\Security\Core\Exception\AccountStatusException;

class UserDisabledException extends AccountStatusException
{
}
