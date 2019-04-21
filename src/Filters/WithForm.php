<?php

namespace BlastCloud\Guzzler\Filters;

use BlastCloud\Guzzler\Interfaces\With;
use GuzzleHttp\Psr7\MultipartStream;
Use BlastCloud\Guzzler\Traits\Helpers;

class WithForm extends Base implements With
{
    use Helpers;

    protected $form = [];
    protected $exclusive = false;

    public function withFormField($key, $value)
    {
        $this->form[$key] = $value;
    }

    public function withForm(array $form, bool $exclusive = false)
    {
        foreach ($form as $key => $value) {
            $this->withFormField($key, $value);
        }

        $this->exclusive = $exclusive;
    }

    public function __invoke(array $history): array
    {
        return array_filter($history, function ($call) {
            $body = $call['request']->getBody();

            if ($body instanceof MultipartStream) {
                $parsed = [];
                foreach ($this->parseMultipartBody($body) as $disp) {
                    if (!$disp->isFile()) $parsed[$disp->name] = $disp->value;
                }
            } else {
                parse_str($body, $parsed);
            }

            return $this->testFields($this->form, $parsed, $this->exclusive);
        });
    }

    public function __toString(): string
    {
        $e = $this->exclusive ? 'true' : 'false';
        return "Form: (Exclusive: {$e}) "
            .json_encode($this->form, JSON_PRETTY_PRINT);
    }
}