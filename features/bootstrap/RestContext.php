<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Features\Bootstrap;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Behatch\Context\RestContext as BaseRestContext;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RestContext implements Context
{
    private ?BaseRestContext $restContext;
    private ?MinkContext $minkContext;
    public array $components = [];
    public string $now = '';

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->restContext = $scope->getEnvironment()->getContext(BaseRestContext::class);
        $this->minkContext = $scope->getEnvironment()->getContext(MinkContext::class);
    }

    /**
     * @AfterScenario
     */
    public function resetNow(): void
    {
        $this->now = '';
    }

    /**
     * @BeforeScenario @saveNow
     */
    public function saveNow(): void
    {
        $this->now = date('Y-m-d\TH:i:s+00:00');
    }

    /**
     * @Given I send a :method request to :url with data:
     */
    public function iSendARequestToWithData($method, $url, TableNode $tableNode)
    {
        $this->restContext->iSendARequestToWithBody($method, $url, new PyStringNode([json_encode($this->castTableNodeToArray($tableNode))], 0));
    }

    /**
     * @When /^I send a "([^"]*)" request to the component "([^"]*)"(?:(?: and the postfix "([^"]*)"|)?(?: with body:|)|)$/i
     */
    public function iSendARequestToTheComponentWithBody(string $method, string $component, ?string $postfix = null, ?PyStringNode $body = null)
    {
        if (!isset($this->components[$component])) {
            throw new ExpectationException("The component with name $component has not been defined", $this->minkContext->getSession()->getDriver());
        }
        $endpoint = $this->components[$component] . ($postfix ?: '');

        return $this->restContext->iSendARequestToWithBody($method, $endpoint, $body ?? new PyStringNode([], 0));
    }

    /**
     * @When /^I send a "([^"]*)" request to the component "([^"]*)"(?: and the postfix "([^"]*)"|)? with data:$/i
     */
    public function iSendARequestToTheComponentWithData(string $method, string $component, TableNode $tableNode, ?string $postfix = null)
    {
        return $this->iSendARequestToTheComponentWithBody($method, $component, $postfix, new PyStringNode([json_encode($this->castTableNodeToArray($tableNode))], 0));
    }

    private function castTableNodeToArray(TableNode $tableNode): array
    {
        return array_map(function ($value) {
            if ('null' === $value) {
                $value = null;
            }

            if ('now' === $value) {
                $this->now = $value = date('Y-m-d\TH:i:s+00:00');
            }

            return $value;
        }, array_combine($tableNode->getRow(0), $tableNode->getRow(1)));
    }
}
