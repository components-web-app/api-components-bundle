<?php

use Behat\Gherkin\Node\PyStringNode;

class RestContext extends \Behatch\Context\RestContext
{
    /**
     * @Given I send a :method request to the entity :entityId with body:
     * @param $method
     * @param string $entityId
     * @param PyStringNode $body
     */
    public function iSendARequestToEntityWithBody($method, string $entityId, PyStringNode $body)
    {
        $this->iSendARequestTo($method, JsonContext::$vars[$entityId], $body);
    }

    /**
     * Sends a HTTP request
     *
     * @Given I send a :method request to the entity :entity
     * @param $method
     * @param string $entity
     * @param PyStringNode|null $body
     * @param array $files
     * @throws Exception
     */
    public function iSendARequestToEntity($method, string $entity, PyStringNode $body = null, $files = [])
    {
        if (!isset(JsonContext::$vars[$entity])) {
            throw new \Exception(sprintf("The variable %s has not been set", $entity));
        }
        $this->iSendARequestTo($method, JsonContext::$vars[$entityId], $body, $files);
    }

    /**
     * Sends a HTTP request
     *
     * @Given I send a :method request to the sub-resource :resource of :entity with body:
     * @param $method
     * @param string $entity
     * @param PyStringNode|null $body
     * @param array $files
     * @throws Exception
     */
    public function iSendARequestToSubresourceOfEntity($method, string $url, string $entity, PyStringNode $body = null, $files = [])
    {
        if (!isset(JsonContext::$vars[$entity])) {
            throw new \Exception(sprintf("The variable %s has not been set", $entity));
        }
        $this->iSendARequestTo($method, JsonContext::$vars[$entity] . $url, $body, $files);
    }
}
