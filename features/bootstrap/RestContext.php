<?php

declare(strict_types=1);

use Behat\Gherkin\Node\PyStringNode;
use Behatch\Context\RestContext as BaseRestContext;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RestContext extends BaseRestContext
{
    public array $components = [];

    /**
     * @When /^I send a "([^"]*)" request to the component "([^"]*)"(?: and the postfix "([^"]*)")? with body:$/i
     */
    public function iSend(string $method, string $component, ?string $postfix, PyStringNode $body)
    {
        if (!isset($this->components[$component])) {
            throw new \Exception("The component with name $component has not been defined");
        }
        $endpoint = $this->components[$component] . ($postfix ?: '');

        return $this->iSendARequestToWithBody($method, $endpoint, $body);
    }
}
