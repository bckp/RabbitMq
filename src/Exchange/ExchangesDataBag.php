<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\Exchange;

use Bckp\RabbitMQ\AbstractDataBag;

final class ExchangesDataBag extends AbstractDataBag
{

	/**
	 * @param array<string, mixed> $config
	 */
	public function addExchangeConfig(string $exchangeName, array $config): void
	{
		$this->data[$exchangeName] = $config;
	}
}
