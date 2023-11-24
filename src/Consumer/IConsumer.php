<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\Consumer;

use Bunny\Message;

interface IConsumer
{

	public const MESSAGE_ACK = 1;
	public const MESSAGE_NACK = 2;
	public const MESSAGE_NACK_REJECT = 3;
	public const MESSAGE_REJECT = 4;
	public const MESSAGE_ACK_AND_TERMINATE = 11;
	public const MESSAGE_NACK_AND_TERMINATE = 12;
	public const MESSAGE_NACK_REJECT_AND_TERMINATE = 13;
	public const MESSAGE_REJECT_AND_TERMINATE = 14;

	public function consume(Message $message): int;
}
