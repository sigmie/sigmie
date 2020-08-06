<?php

namespace Sigma\Query;

class Match
{
    public function all(): self
    {
        return $this;
    }

    public function phrase(): self
    {
        return $this;
    }
}
