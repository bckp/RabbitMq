<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\Exchange;

use Bckp\RabbitMQ\Connection\ConnectionFactory;
use Bckp\RabbitMQ\Exchange\Exception\ExchangeFactoryException;
use Bckp\RabbitMQ\Queue\Exception\QueueFactoryException;
use Bckp\RabbitMQ\Queue\QueueFactory;

final class ExchangeFactory
{

	/**
	 * @var IExchange[]
	 */
	private array $exchanges = [];

	public function __construct(
		private ExchangesDataBag $exchangesDataBag,
		private QueueFactory $queueFactory,
		private ExchangeDeclarator $exchangeDeclarator,
		private ConnectionFactory $connectionFactory
	) {
	}

	/**
	 * @throws ExchangeFactoryException
	 */
	public function getExchange(string $name): IExchange
	{
		if (!isset($this->exchanges[$name])) {
			$this->exchanges[$name] = $this->create($name);
		}

		return $this->exchanges[$name];
	}

	/**
	 * @throws ExchangeFactoryException
	 * @throws QueueFactoryException
	 */
	private function create(string $name): IExchange
	{
		$queueBindings = [];

		try {
			$exchangeData = $this->exchangesDataBag->getDataByKey($name);
		} catch (\InvalidArgumentException) {
			throw new ExchangeFactoryException("Exchange [$name] does not exist");
		}

		$connection = $this->connectionFactory->getConnection($exchangeData['connection']);

		if ($exchangeData['autoCreate'] === 1) {
			$this->exchangeDeclarator->declareExchange($name);
		}

		if ($exchangeData['queueBindings'] !== []) {
			foreach ($exchangeData['queueBindings'] as $queueName => $queueBinding) {
				// (QueueFactoryException)
				$queue = $this->queueFactory->getQueue($queueName);

				$queueBindings[] = new QueueBinding(
					$queue,
					$queueBinding['routingKey']
				);
			}
		}

		return new Exchange(
			$name,
			$queueBindings,
			$connection
		);
	}
}
