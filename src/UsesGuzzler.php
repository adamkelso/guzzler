<?php

namespace BlastCloud\Guzzler;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait UsesGuzzler
{
    public Guzzler $guzzler;

    #[Before]
    public function setUpGuzzler()
    {
        $this->guzzler = new Guzzler($this);
    }

    /**
     * Run through the list of expectations that were made and
     * evaluate all requests in the history. Closure::call()
     * is used to hide this method from the user APIs.
     */
    #[After]
    public function runGuzzlerAssertions(): void
    {
        (function () {
            $this->runExpectations();
        })->call($this->guzzler);
    }
}