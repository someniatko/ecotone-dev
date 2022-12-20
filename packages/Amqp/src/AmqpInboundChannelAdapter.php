<?php

declare(strict_types=1);

namespace Ecotone\Amqp;

use Ecotone\Enqueue\CachedConnectionFactory;
use Ecotone\Enqueue\EnqueueInboundChannelAdapter;
use Ecotone\Enqueue\InboundMessageConverter;
use Ecotone\Messaging\Conversion\MediaType;
use Ecotone\Messaging\Endpoint\InboundChannelAdapterEntrypoint;
use Ecotone\Messaging\Endpoint\PollingConsumer\ConnectionException;
use Ecotone\Messaging\Endpoint\PollingMetadata;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\Scheduling\TaskExecutor;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Messaging\Support\MessageBuilder;
use Interop\Amqp\AmqpMessage;
use Interop\Queue\Message as EnqueueMessage;
use Throwable;

/**
 * Class InboundEnqueueGateway
 * @package Ecotone\Amqp
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class AmqpInboundChannelAdapter extends EnqueueInboundChannelAdapter
{
    public function __construct(
        CachedConnectionFactory $cachedConnectionFactory,
        InboundChannelAdapterEntrypoint $inboundAmqpGateway,
        private AmqpAdmin $amqpAdmin,
        bool $declareOnStartup,
        private string $amqpQueueName,
        int $receiveTimeoutInMilliseconds,
        InboundMessageConverter $inboundMessageConverter
    ) {
        parent::__construct(
            $cachedConnectionFactory,
            $inboundAmqpGateway,
            $declareOnStartup,
            new \Interop\Amqp\Impl\AmqpQueue($amqpQueueName),
            $receiveTimeoutInMilliseconds,
            $inboundMessageConverter
        );
    }

    public function initialize(): void
    {
        $this->amqpAdmin->declareQueueWithBindings($this->amqpQueueName, $this->cachedConnectionFactory->createContext());
    }

    /**
     * @param AmqpMessage $sourceMessage
     */
    public function enrichMessage(EnqueueMessage $sourceMessage, MessageBuilder $targetMessage): MessageBuilder
    {
        if ($sourceMessage->getContentType()) {
            $targetMessage = $targetMessage->setContentType(MediaType::parseMediaType($sourceMessage->getContentType()));
        }

        return $targetMessage;
    }
}
