<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\DI\Helpers;

use Bckp\RabbitMQ\AbstractDataBag;
use Bckp\RabbitMQ\Queue\QueueDeclarator;
use Bckp\RabbitMQ\Queue\QueueFactory;
use Bckp\RabbitMQ\Queue\QueuesDataBag;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
use Nette\Schema\Schema;

final class QueuesHelper extends AbstractHelper
{
	public function getConfigSchema(): Schema
	{
		return Expect::arrayOf(
			$this->getQueueSchema(),
			'string'
		);
	}

	public function getQueueSchema(): Schema
	{
		return Expect::structure([
			'connection' => Expect::string('default'),
			'passive' => Expect::bool(false),
			'durable' => Expect::bool(true),
			'exclusive' => Expect::bool(false),
			'autoDelete' => Expect::bool(false),
			'noWait' => Expect::bool(false),
			'arguments' => Expect::array(),
			'dlx' => Expect::arrayOf(Expect::int()->min(1))->required(false)->before(
				fn(array $dlx): array => $this->normalizeDlx($dlx)
			),
			'autoCreate' => Expect::int(
				AbstractDataBag::AutoCreateLazy
			)->before(
				fn(mixed $input): int => $this->normalizeAutoDeclare($input)
			),
		])->castTo('array');
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{connection: string, passive: bool, durable: bool, exclusive: bool, autoDelete: bool, noWait: bool, arguments: array<string, mixed>, dlx: int[], autoCreate: int}
	 */
	public function processConfiguration(array $data): array
	{
		return (new Processor)->process($this->getQueueSchema(), $data);
	}

	/**
	 * @param array<string, mixed> $config
	 */
	public function setup(ContainerBuilder $builder, array $config = []): ServiceDefinition
	{
		$queuesDataBag = $builder
			->addDefinition($this->extension->prefix('queuesDataBag'))
			->setFactory(QueuesDataBag::class)
			->setArguments([$config]);

		$builder
			->addDefinition($this->extension->prefix('queueDeclarator'))
			->setFactory(QueueDeclarator::class);

		return $builder
			->addDefinition($this->extension->prefix('queueFactory'))
			->setFactory(QueueFactory::class)
			->setArguments([$queuesDataBag]);
	}

	/**
	 * @param string[] $dlx
	 * @return int[]
	 */
	protected function normalizeDlx(array $dlx): array
	{
		return array_map(static fn(string $time): int => (int) strtotime($time, 0), $dlx);
	}
}
