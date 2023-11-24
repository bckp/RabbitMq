<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\Producer;

use Bckp\RabbitMQ\Exchange\ExchangeFactory;
use Bckp\RabbitMQ\LazyDeclarator;
use Bckp\RabbitMQ\Producer\Exception\ProducerFactoryException;
use Bckp\RabbitMQ\Queue\QueueFactory;

final class ProducerFactory
{

	/**
	 * @var callable[]
	 */
	public array $createdCallbacks = [];

	/**
	 * @var IProducer[]
	 */
	private array $producers = [];

	public function __construct(
		private ProducersDataBag $producersDataBag,
		private QueueFactory $queueFactory,
		private ExchangeFactory $exchangeFactory,
		private LazyDeclarator $lazyDeclarator
	) {
	}

	/**
	 * @throws ProducerFactoryException
	 */
	public function getProducer(string $name): IProducer
	{
		if (!isset($this->producers[$name])) {
			$this->producers[$name] = $this->create($name);
		}

		return $this->producers[$name];
	}

	public function addOnCreatedCallback(callable $callback): void
	{
		$this->createdCallbacks[] = $callback;
	}

	/**
	 * @throws ProducerFactoryException
	 */
	private function create(string $name): IProducer
	{
		try {
			$producerData = $this->producersDataBag->getDataByKey($name);
		} catch (\InvalidArgumentException) {
			throw new ProducerFactoryException("Producer [$name] does not exist");
		}

		$exchange = null;
		$queue = null;

		if ($producerData['exchange']) {
			$exchange = $this->exchangeFactory->getExchange($producerData['exchange']);
		}

		if ($producerData['queue']) {
			$queue = $this->queueFactory->getQueue($producerData['queue']);
		}

		$producer = new Producer(
			$exchange,
			$queue,
			$producerData['contentType'],
			$producerData['deliveryMode'],
			$this->lazyDeclarator,
		);

		foreach ($this->createdCallbacks as $callback) {
			($callback)($name, $producer);
		}

		return $producer;
	}
}
