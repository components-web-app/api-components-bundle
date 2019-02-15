<?php

namespace Silverback\ApiComponentBundle\Security;

class TokenGenerator
{
    public function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
