<?php

namespace Silverback\ApiComponentBundle\Imagine;

class PathResolver
{
    /** @var array */
    private $roots;

    public function __construct(
        array $roots = []
    ) {
        $this->roots = $roots;
    }

    public function resolve($path):string
    {
        foreach ($this->roots as $root) {
            if (mb_strpos($path, $root) === 0) {
                return mb_substr($path, \strlen($root));
            }
        }
        return $path;
    }
}
