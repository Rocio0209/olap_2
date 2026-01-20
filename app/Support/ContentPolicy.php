<?php

namespace App\Support;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policies\Basic;

class ContentPolicy extends Basic
{
    public function configure()
    {
        $this->addDirective(Directive::SCRIPT, Keyword::UNSAFE_EVAL);
        //$this->addDirective(Directive::SCRIPT, Keyword::UNSAFE_INLINE);
        $this->addNonceForDirective(Directive::SCRIPT);
        $this->addDirective(Directive::BASE, Keyword::NONE);
        $this->addDirective(Directive::OBJECT, Keyword::NONE);
    }
}
