<?php

namespace Monorepo\ExampleApp\Common\Domain\Article\Command;

class CreatePublication
{
    public function __construct(public string $id, public string $title, public string $content)
    {
    }
}