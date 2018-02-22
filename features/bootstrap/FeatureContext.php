<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behatch\HttpCall\Request;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    /**
     * @var Request\BrowserKit
     */
    private $request;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Sets the default Accept HTTP header to null (workaround to artificially remove it).
     * @AfterStep
     * @param AfterStepScope $event
     */
    public function removeAcceptHeaderAfterRequest(AfterStepScope $event)
    {
        if (preg_match('/^I send a "[A-Z]+" request to ".+"/', $event->getStep()->getText())) {
            $this->request->setHttpHeader('Accept', null);
        }
    }

    /**
     * Sets the default Accept HTTP header to null (workaround to artificially remove it).
     *
     * @BeforeScenario
     */
    public function removeAcceptHeaderBeforeScenario()
    {
        $this->request->setHttpHeader('Accept', null);
    }
}
