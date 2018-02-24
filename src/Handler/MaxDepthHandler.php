<?php

namespace Silverback\ApiComponentBundle\Handler;

class MaxDepthHandler
{
    public function __invoke($obj) {
        return $obj->id;
    }
}
