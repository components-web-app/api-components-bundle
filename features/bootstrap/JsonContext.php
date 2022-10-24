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

use ApiPlatform\Symfony\Bundle\Test\Constraint\ArraySubset;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behatch\Context\JsonContext as BehatchJsonContext;
use Behatch\Json\Json;
use Behatch\Json\JsonInspector;
use Behatch\Json\JsonSchema;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Cookie;

class JsonContext implements Context
{
    private JsonInspector $inspector;
    private ?BehatchJsonContext $jsonContext;
    private ?RestContext $restContext;
    private JWSProviderInterface $jwsProvider;

    public function __construct(JWSProviderInterface $jwsProvider)
    {
        $this->inspector = new JsonInspector('javascript');
        $this->jwsProvider = $jwsProvider;
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
        self::assertArraySubset(json_decode($content->getRaw(), true), $this->getJsonAsArray());
    }

    public static function assertArraySubset($subset, $array, bool $checkForObjectIdentity = false, string $message = ''): void
    {
        $constraint = new ArraySubset($subset, $checkForObjectIdentity);

        Assert::assertThat($array, $constraint, $message);
    }

    private function sortArrays($obj)
    {
        $isObject = \is_object($obj);

        foreach ($obj as $key => $value) {
            if (null === $value || \is_scalar($value)) {
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
     * @Then the response should be the resource :name
     */
    public function theResponseShouldBeTheResource($name): void
    {
        $response = $this->getJsonAsArray();
        Assert::assertArrayHasKey('@id', $response);
        Assert::assertEquals($this->restContext->resources[$name], $response['@id']);
    }

    /**
     * @Then the response should have a :name cookie
     */
    public function theResponseShouldHaveACookie(string $name): void
    {
        $responseHeaders = $this->jsonContext->getSession()->getResponseHeaders();
        $setCookieHeaders = $responseHeaders['set-cookie'];
        foreach ($setCookieHeaders as $setCookieHeader) {
            $cookie = Cookie::fromString($setCookieHeader);
            $realName = $cookie->getName();
            if ($realName === $name) {
                return;
            }
        }
        throw new \Exception(sprintf('The cookie "%s" was not found in the response headers.', $name));
    }

    private function getMercureCookieDraftTopics(): array
    {
        $responseHeaders = $this->jsonContext->getSession()->getResponseHeaders();
        $setCookieHeaders = $responseHeaders['set-cookie'];
        foreach ($setCookieHeaders as $setCookieHeader) {
            $cookie = Cookie::fromString($setCookieHeader);
            $realName = $cookie->getName();
            if ('mercureAuthorization' === $realName) {
                $token = $this->jwsProvider->load($cookie->getValue());
                $payload = $token->getPayload();

                return array_filter($payload['mercure']['subscribe'], static function ($topic) {
                    return str_ends_with($topic, '?draft=1');
                });
            }
        }

        return [];
    }

    /**
     * @Then the mercure cookie should not contain draft resource topics
     */
    public function theMercureCookieShouldNotContainDraftResources()
    {
        Assert::assertCount(0, $this->getMercureCookieDraftTopics(), 'The cookie allows a user to be subscribed to draft resources');
    }

    /**
     * @Then the mercure cookie should contain draft resource topics
     */
    public function theMercureCookieShouldContainDraftResources()
    {
        Assert::assertGreaterThan(0, $this->getMercureCookieDraftTopics(), 'The cookie does not allow a user to subscribe to any draft resources');
    }

    /**
     * @Then the response should have a :name cookie with max age less than :seconds
     */
    public function theResponseShouldHaveACookieWithMaxAgeLessThan(string $name, int $seconds): void
    {
        $cookie = Cookie::fromString($this->jsonContext->getSession()->getResponseHeader('set-cookie'));
        $timeDiff = $cookie->getExpiresTime() - time();
        Assert::assertLessThan($seconds, $timeDiff, sprintf('The cookie "%s" expires in "%d" seconds. Expected less than "%d" seconds', $name, $timeDiff, $seconds));
    }

    /**
     * @Then /^the response should have a "(.+)" cookie with the value "(.+)?"$/
     */
    public function theResponseShouldHaveACookieWithTheValue(string $name, ?string $value = null): void
    {
        $cookie = Cookie::fromString($this->jsonContext->getSession()->getResponseHeader('set-cookie'));
        $real = $cookie->getValue();
        Assert::assertEquals($value, $real, sprintf('The cookie "%s" has the value "%s". Expected "%s"', $name, $real, $value));
    }

    /**
     * @Then the JSON node :node should be now
     */
    public function theJsonNodeShouldBeEqualTo($node): void
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
     * @Then the JSON node :name should be equal to the IRI of the resource :resource
     */
    public function theJsonNodeShouldBeEqualToTheIriOfTheResource(string $name, string $resource): void
    {
        $this->jsonContext->theJsonNodeShouldBeEqualTo($name, $this->restContext->resources[$resource]);
    }

    /**
     * @Then I save the JSON node :name as the resource :resource
     */
    public function iSaveTheJsonNodeAsTheResource(string $name, string $resource): void
    {
        $json = $this->getJson();

        $actual = $this->inspector->evaluate($json, $name);

        $this->restContext->resources[$resource] = $actual;
    }

    /**
     * @Then the JSON node :name should match the regex :expression
     */
    public function theJsonNodeShouldMatchTheRegex(string $name, string $expression): void
    {
        $json = $this->getJson();

        $actual = $this->inspector->evaluate($json, $name);

        if (1 !== preg_match($expression, $actual)) {
            throw new \Exception(sprintf("The node value did not match '%s'. It is '%s'", $expression, json_encode($actual)));
        }
    }

    private function getJson(): Json
    {
        return new Json($this->getContent());
    }

    private function getContent(): string
    {
        return $this->jsonContext->getSession()->getPage()->getContent();
    }

    public function getJsonAsArray(): array
    {
        return json_decode($this->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
