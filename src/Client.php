<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ;

use Bckp\RabbitMQ\Producer\Exception\ProducerFactoryException;
use Bckp\RabbitMQ\Producer\IProducer;
use Bckp\RabbitMQ\Producer\ProducerFactory;

/**
 * This package uses composer library bunny/bunny. For more information,
 * @see https://github.com/jakubkulhan/bunny
 */
final class Client
{

	public function __construct(private ProducerFactory $producerFactory)
	{
	}


	/**
	 * @throws ProducerFactoryException
	 */
	public function getProducer(string $name): IProducer
	{
		return $this->producerFactory->getProducer($name);
	}
}
