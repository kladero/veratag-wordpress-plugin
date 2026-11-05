<?php

namespace VeraTag\Logging;

interface Logger
{
    public function error(string $message): void;
}
