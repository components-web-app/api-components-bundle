<?php

use Behat\Gherkin\Node\PyStringNode;
use Behatch\Context\JsonContext as BaseJsonContext;

final class JsonContext extends BaseJsonContext
{
    private static $vars = [];
    private static $jsonVars = [];

    /**
     * @param $var
     * @return \Behatch\Json\Json
     * @throws Exception
     */
    public static function getJsonVar($var): \Behatch\Json\Json
    {
        if (!isset(self::$jsonVars[$var])) {
            throw new \Exception(sprintf("The JSON variable %s has not been set", $var));
        }
        return self::$jsonVars[$var];
    }

    /**
     * @param $var
     * @return mixed
     * @throws Exception
     */
    public static function getVar($var)
    {
        if (!isset(self::$vars[$var])) {
            throw new \Exception(sprintf("The variable %s has not been set", $var));
        }
        return self::$vars[$var];
    }

    /**
     * @Then save the entity id as :var
     * @param string $var
     * @throws Exception
     */
    public function saveEntityIdAsVar(string $var)
    {
        $json = $this->getJson();
        $id = $this->inspector->evaluate($json, '@id');
        static::$vars[$var] = $id;
    }

    /**
     * @Given the json variable :name is:
     * @param string $name
     * @param PyStringNode $var
     */
    public function saveJsonVar(string $name, PyStringNode $var)
    {
        self::$jsonVars[$name] = new \Behatch\Json\Json($var);
    }

    /**
     * @Given the node :node of the json variable :name is equal to the variable :value
     * @param $node
     * @param $name
     * @param $value
     * @throws Exception
     */
    public function setJsonVarNode($node, $name, $value)
    {
        $jsonData = self::getJsonVar($name)->getContent();
        $jsonData->$node = self::getVar($value);
        $this->saveJsonVar($name, new PyStringNode([json_encode($jsonData)], 1));
    }
}
