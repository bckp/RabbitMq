<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\Queue;

use Bckp\RabbitMQ\Connection\IConnection;

interface IQueue
{

	public function getName(): string;


	public function getConnection(): IConnection;
}
