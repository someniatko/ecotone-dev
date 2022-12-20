<?php

namespace Ecotone\Dbal;

use Ecotone\Enqueue\CachedConnectionFactory;
use Ecotone\Enqueue\InboundMessageConverter;
use Ecotone\Messaging\Endpoint\InboundChannelAdapterEntrypoint;
use Ecotone\Messaging\Endpoint\PollingMetadata;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\Scheduling\TaskExecutor;
use Enqueue\Dbal\DbalContext;
use Enqueue\Dbal\DbalDestination;
use Enqueue\Dbal\DbalMessage;
use Interop\Queue\Destination;
use Interop\Queue\Message as EnqueueMessage;

class DbalInboundChannelAdapter implements TaskExecutor
{
    public function __construct(
        private CachedConnectionFactory $cachedConnectionFactory,
        private InboundChannelAdapterEntrypoint $entrypointGateway,
        private bool $declareOnStartup,
        private string $queueName,
        private Destination $destination,
        private int $receiveTimeoutInMilliseconds,
        private InboundMessageConverter $inboundMessageConverter
    ) {}

    public function execute(PollingMetadata $pollingMetadata): void
    {
        $message = $this->receiveMessage($pollingMetadata->getExecutionTimeLimitInMilliseconds());

        if ($message) {
            $this->entrypointGateway->executeEntrypoint($message);
        }
    }

    public function receiveMessage(int $timeout = 0): ?Message
    {
        if (! $this->declareOnStartup) {
            /** @var DbalContext $context */
            $context = $this->cachedConnectionFactory->createContext();

            $context->createDataBaseTable();
            $context->createQueue($this->queueName);
            $this->declareOnStartup = false;
        }

        $consumer = $this->cachedConnectionFactory->getConsumer($this->destination);

        /** @var EnqueueMessage $message */
        $message = $consumer->receive($timeout ?: $this->receiveTimeoutInMilliseconds);

        if (! $message) {
            return null;
        }

        return $this->inboundMessageConverter->toMessage($message, $consumer)->build();
    }
}
