<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behatch\Context\JsonContext as BehatchJsonContext;
use Behatch\Json\Json;
use Behatch\Json\JsonInspector;
use Behatch\Json\JsonSchema;
use PHPUnit\Framework\Assert;

class JsonContext implements Context
{
    private JsonInspector $inspector;
    private ?BehatchJsonContext $jsonContext;
    private ?RestContext $restContext;

    public function __construct()
    {
        $this->inspector = new JsonInspector('javascript');
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->jsonContext = $scope->getEnvironment()->getContext(BehatchJsonContext::class);
        $this->restContext = $scope->getEnvironment()->getContext(RestContext::class);
    }

    /**
     * @Then /^the JSON should be deep equal to:$/
     */
    public function theJsonShouldBeDeepEqualTo(PyStringNode $content): void
    {
        $actual = $this->getJson();
        try {
            $expected = new Json($content);
        } catch (\Exception $e) {
            throw new \Exception('The expected JSON is not a valid');
        }

        $actual = new Json(json_encode($this->sortArrays($actual->getContent())));
        $expected = new Json(json_encode($this->sortArrays($expected->getContent())));

        $this->jsonContext->assertSame($expected->getContent(), $actual->getContent(), "The json is equal to:\n" . $actual->encode());
    }

    /**
     * @Then /^the JSON should be a superset of:$/
     */
    public function theJsonIsASupersetOf(PyStringNode $content): void
    {
        // Must change to https://github.com/rdohms/phpunit-arraysubset-asserts for PHPUnit 9
        Assert::assertArraySubset(json_decode($content->getRaw(), true), $this->getJsonAsArray());
    }

    private function sortArrays($obj)
    {
        $isObject = \is_object($obj);

        foreach ($obj as $key => $value) {
            if (null === $value || is_scalar($value)) {
                continue;
            }

            if (\is_array($value)) {
                sort($value);
            }

            $value = $this->sortArrays($value);

            $isObject ? $obj->{$key} = $value : $obj[$key] = $value;
        }

        return $obj;
    }

    /**
     * @Then the JSON should be valid according to the schema file :file
     */
    public function theJsonShouldBeValidAccordingToTheSchemaFile(string $file): void
    {
        $this->jsonContext->theJsonShouldBeValidAccordingToTheSchema(__DIR__ . '/../assets/schema/' . $file);
    }

    /**
     * @Then the JSON should be an array with each entry valid according to the schema file :file
     */
    public function theJsonShouldBeAnArrayWithEachEntryValidAccordingToTheSchemaFile(string $file): void
    {
        $json = $this->getJson();
        $schema = new PyStringNode([file_get_contents(__DIR__ . '/../assets/schema/' . $file)], 1);
        try {
            foreach ($json as $item) {
                $this->inspector->validate($item, new JsonSchema($schema));
            }
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage() . "\n\nThe json is equal to:\n" . $this->getJson()->encode());
        }
    }

    /**
     * @Then the response should not include the key :arrayKey
     */
    public function theResponseShouldNotIncludeTheKeyWithValue($arrayKey): void
    {
        $response = $this->getJsonAsArray();
        Assert::assertArrayNotHasKey($arrayKey, $response);
    }

    /**
     * @Then the response should be the component :name
     */
    public function theResponseShouldBeTheComponent($name): void
    {
        $response = $this->getJsonAsArray();
        Assert::assertArrayHasKey('@id', $response);
        Assert::assertEquals($this->restContext->components[$name], $response['@id']);
    }

    /**
     * @Then the JSON node :node should be now
     */
    public function theJsonNodeShouldBeEqualTo($node)
    {
        $text = $this->restContext->getCachedNow();
        $json = $this->getJson();

        $actual = $this->inspector->evaluate($json, $node);
        $diff = (new \DateTime($text))->getTimestamp() - (new \DateTime($actual))->getTimestamp();
        if ($diff < -1 || $diff > 1) {
            throw new \Exception(sprintf("The node value is '%s' which is a difference of %s seconds to the cached 'now' value", json_encode($actual), $diff));
        }
    }

    /**
     * @Then the JSON node :name should be equal to the IRI of the component :component
     */
    public function theJsonNodeShouldBeEqualToTheIriOfTheComponent(string $name, string $component)
    {
        $this->jsonContext->theJsonNodeShouldBeEqualTo($name, $this->restContext->components[$component]);
    }

    /**
     * @Then I save the JSON node :name as the component :component
     */
    public function iSaveTheJsonNodeAsTheComponent(string $name, string $component)
    {
        $json = $this->getJson();

        $actual = $this->inspector->evaluate($json, $name);

        $this->restContext->components[$component] = $actual;
    }

    private function getJson()
    {
        return new Json($this->getContent());
    }

    private function getContent(): string
    {
        return $this->jsonContext->getSession()->getPage()->getContent();
    }

    public function getJsonAsArray(): array
    {
        return json_decode($this->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
