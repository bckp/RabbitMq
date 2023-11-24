<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\Exchange;

use Bckp\RabbitMQ\Connection\IConnection;

interface IExchange
{

	public function getName(): string;

	/**
	 * @return QueueBinding[]
	 */
	public function getQueueBindings(): array;


	public function getConnection(): IConnection;
}
