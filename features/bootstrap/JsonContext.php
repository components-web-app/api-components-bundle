<?php

use Behatch\Context\JsonContext as BaseJsonContext;

final class JsonContext extends BaseJsonContext
{
    public static $vars = [];

    /**
     * @Then save the entity id as :var
     * @param string $var
     * @throws Exception
     */
    public function saveEntityId(string $var)
    {
        $json = $this->getJson();
        $id = $this->inspector->evaluate($json, '@id');
        static::$vars[$var] = $id;
    }
}
