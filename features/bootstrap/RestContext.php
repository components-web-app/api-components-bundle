<?php

use Behat\Gherkin\Node\PyStringNode;

class RestContext extends \Behatch\Context\RestContext
{
    /**
     * @Given I send a :method request to the entity :entityId with body:
     * @param $method
     * @param string $entityId
     * @param PyStringNode $body
     * @throws Exception
     */
    public function iSendARequestToEntityWithBody($method, string $entityId, PyStringNode $body)
    {
        $this->iSendARequestTo($method, JsonContext::getVar($entityId), $body);
    }

    /**
     * Sends a HTTP request
     *
     * @Given I send a :method request to the entity :entity
     * @param $method
     * @param string $entity
     * @throws Exception
     */
    public function iSendARequestToEntity($method, string $entity)
    {
        $this->iSendARequestTo($method, JsonContext::getVar($entity), null, []);
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
        $this->iSendARequestTo($method, JsonContext::getVar($entity) . $url, $body, $files);
    }

    /**
     * @Given I send a :method request to :url with the json variable :var as the body
     * @throws Exception
     */
    public function iSendARequestToWithBodyFromJsonVar($method, $url, $var)
    {
        $this->iSendARequestTo($method, $url, new PyStringNode([JsonContext::getJsonVar($var)->encode()], 1), []);
    }
}
