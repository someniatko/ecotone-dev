<?php

namespace Ecotone\Messaging\Config;

use Ecotone\Messaging\Gateway\MessagingEntrypoint;
use Ecotone\Messaging\Support\InvalidArgumentException;

class ConsoleCommandRunner
{
    public function __construct(private MessagingEntrypoint $entrypoint, private ConsoleCommandConfiguration $commandConfiguration)
    {
    }

    public function run(array $parameters): mixed
    {
        $arguments = [];

        foreach ($parameters as $argumentName => $value) {
            if (! $this->hasParameterWithGivenName($argumentName)) {
                continue;
            }

            $arguments[$this->commandConfiguration->getHeaderNameForParameterName($argumentName)] = $value;
        }
        foreach ($this->commandConfiguration->getParameters() as $commandParameter) {
            if (! array_key_exists($this->commandConfiguration->getHeaderNameForParameterName($commandParameter->getName()), $arguments)) {
                if (! $commandParameter->hasDefaultValue()) {
                    throw InvalidArgumentException::create("Missing argument with name {$commandParameter->getName()} for console command {$this->commandConfiguration->getName()}");
                }

                $arguments[$this->commandConfiguration->getHeaderNameForParameterName($commandParameter->getName())] = $commandParameter->getDefaultValue();
            }
        }

        return $this->entrypoint->sendWithHeaders([], $arguments, $this->commandConfiguration->getChannelName());
    }

    private function hasParameterWithGivenName(int|string $argumentName): bool
    {
        foreach ($this->commandConfiguration->getParameters() as $commandParameter) {
            if ($commandParameter->getName() === $argumentName) {
                return true;
            }
        }

        return false;
    }
}
