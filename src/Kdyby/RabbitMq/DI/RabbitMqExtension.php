<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\RabbitMq\DI;

use Kdyby;
use Nette;
use Nette\DI\Compiler;
use Nette\Utils\Validators;
use Kdyby\RabbitMq\IProducer;
use Kdyby\RabbitMq\MultipleConsumer;
use Kdyby\RabbitMq\Consumer;
use Kdyby\RabbitMq\AnonymousConsumer;
use Kdyby\RabbitMq\RpcClient;
use Kdyby\RabbitMq\RpcServer;
use Kdyby\RabbitMq\Command\StdInProducerCommand;
use Kdyby\RabbitMq\Command\SetupFabricCommand;
use Kdyby\RabbitMq\Command\RpcServerCommand;
use Kdyby\RabbitMq\Command\PurgeConsumerCommand;
use Kdyby\RabbitMq\Command\ConsumerCommand;
use Kdyby\RabbitMq\Producer;
use Kdyby\RabbitMq\Diagnostics\Panel;
use Kdyby\RabbitMq\Connection;


/**
 * @author Alvaro Videla <videlalvaro@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class RabbitMqExtension extends Nette\DI\CompilerExtension
{

	public const TAG_PRODUCER = 'kdyby.rabbitmq.producer';
	public const TAG_CONSUMER = 'kdyby.rabbitmq.consumer';
	public const TAG_RPC_CLIENT = 'kdyby.rabbitmq.rpc.client';
	public const TAG_RPC_SERVER = 'kdyby.rabbitmq.rpc.server';

	public const EXTENDS_KEY = '_extends';
	public const OVERWRITE = true;

	/**
	 * @var array
	 */
	public $defaults = [
		'connection' => [],
		'producers' => [],
		'consumers' => [],
		'rpcClients' => [],
		'rpcServers' => [],
		'debugger' => '%debugMode%',
		'autoSetupFabric' => '%debugMode%',
		'consoleNamespace' => 'kdybyrabbitmq',
		'consoleAlias' => null,
	];

	/**
	 * @var array
	 */
	public $connectionDefaults = [
		'host' => '127.0.0.1',
		'port' => 5672,
		'user' => NULL,
		'password' => NULL,
		'vhost' => '/',
	];

	/**
	 * @var array
	 */
	public $producersDefaults = [
		'connection' => 'default',
		'class' => Producer::class,
		'exchange' => [],
		'queue' => [],
		'contentType' => 'text/plain',
		'deliveryMode' => 2,
		'routingKey' => '',
		'autoSetupFabric' => NULL, // inherits from `rabbitmq: autoSetupFabric:`
	];

	/**
	 * @var array
	 */
	public $consumersDefaults = [
		'connection' => 'default',
		'exchange' => [],
		'queues' => [], // for multiple consumers
		'queue' => [], // for single consumer
		'callback' => NULL,
		'qos' => [],
		'idleTimeout' => NULL,
		'autoSetupFabric' => NULL, // inherits from `rabbitmq: autoSetupFabric:`
	];

	/**
	 * @var array
	 */
	public $rpcClientDefaults = [
		'connection' => 'default',
		'expectSerializedResponse' => TRUE,
	];

	/**
	 * @var array
	 */
	public $rpcServerDefaults = [
		'connection' => 'default',
		'callback' => NULL,
		'qos' => [],
	];

	/**
	 * @var array
	 */
	public $exchangeDefaults = [
		'passive' => FALSE,
		'durable' => TRUE,
		'autoDelete' => FALSE,
		'internal' => FALSE,
		'nowait' => FALSE,
		'arguments' => NULL,
		'ticket' => NULL,
		'declare' => TRUE,
	];

	/**
	 * @var array
	 */
	public $queueDefaults = [
		'name' => '',
		'passive' => FALSE,
		'durable' => TRUE,
		'noLocal' => FALSE,
		'noAck' => FALSE,
		'exclusive' => FALSE,
		'autoDelete' => FALSE,
		'nowait' => FALSE,
		'arguments' => NULL,
		'ticket' => NULL,
		'routing_keys' => [],
	];

	/**
	 * @var array
	 */
	public $qosDefaults = [
		'prefetchSize' => 0,
		'prefetchCount' => 0,
		'global' => FALSE,
	];

	/**
	 * @var array
	 */
	protected $connectionsMeta = [];

	/**
	 * @var array
	 */
	private $producersConfig = [];

	/**
	 * @throws Nette\Utils\AssertionException
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->mergeConfig($this->getConfig(), $this->defaults);

		foreach ($this->compiler->getExtensions() as $extension) {
			if ($extension instanceof IProducersProvider) {
				$producers = $extension->getRabbitProducers();
				Validators::assert($producers, 'array:1..');
				$config['producers'] = array_merge($config['producers'], $producers);
			}
			if ($extension instanceof IConsumersProvider) {
				$consumers = $extension->getRabbitConsumers();
				Validators::assert($consumers, 'array:1..');
				$config['consumers'] = array_merge($config['consumers'], $consumers);
			}
			if ($extension instanceof IRpcClientsProvider) {
				$rpcClients = $extension->getRabbitRpcClients();
				Validators::assert($rpcClients, 'array:1..');
				$config['rpcClients'] = array_merge($config['rpcClients'], $rpcClients);
			}
			if ($extension instanceof IRpcServersProvider) {
				$rpcServers = $extension->getRabbitRpcServers();
				Validators::assert($rpcServers, 'array:1..');
				$config['rpcServers'] = array_merge($config['rpcServers'], $rpcServers);
			}
		}

		if ($unexpected = array_diff(array_keys($config), array_keys($this->defaults))) {
			throw new Nette\Utils\AssertionException("Unexpected key '" . implode("', '", $unexpected) . "' in configuration of {$this->name}.");
		}

		$builder->parameters[$this->name] = $config;

		$this->loadConnections($config['connection']);
		$this->loadProducers($config['producers']);
		$this->loadConsumers($config['consumers']);
		$this->loadRpcClients($config['rpcClients']);
		$this->loadRpcServers($config['rpcServers']);

		foreach ($this->connectionsMeta as $name => $meta) {
			$connection = $builder->getDefinition($meta['serviceId']);

			if ($config['debugger']) {
				$builder->addDefinition($panelService = $meta['serviceId'] . '.panel')
					->setType(Panel::class)
					->addSetup('injectServiceMap', [
						$meta['consumers'],
						$meta['rpcServers'],
					])
					->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT, false)
					->setAutowired(FALSE);

				$connection->addSetup('injectPanel', ['@' . $panelService]);
			}

			$connection->addSetup('injectServiceLocator');
			$connection->addSetup('injectServiceMap', [
				$meta['producers'],
				$meta['consumers'],
				$meta['rpcClients'],
				$meta['rpcServers'],
			]);
		}

		$this->loadConsole($config);
	}

	public function beforeCompile(): void
	{
		unset($this->getContainerBuilder()->parameters[$this->name]);
	}

	/**
	 * @param $connections
	 * @throws Nette\Utils\AssertionException
	 */
	protected function loadConnections($connections): void
	{
		$this->connectionsMeta = []; // reset

		if (isset($connections['user'])) {
			$connections = ['default' => $connections];
		}

		$builder = $this->getContainerBuilder();
		foreach ($connections as $name => $config) {
			$config = $this->mergeConfig($config, $this->connectionDefaults);

			Nette\Utils\Validators::assertField($config, 'user', 'string:3..', "The config item '%' of connection {$this->name}.{$name}");
			Nette\Utils\Validators::assertField($config, 'password', 'string:3..', "The config item '%' of connection {$this->name}.{$name}");

			$connection = $builder->addDefinition($serviceName = $this->prefix($name . '.connection'))
				->setType(Connection::class)
				->setArguments([
					$config['host'],
					$config['port'],
					$config['user'],
					$config['password'],
					$config['vhost']
				]);

			$this->connectionsMeta[$name] = [
				'serviceId' => $serviceName,
				'producers' => [],
				'consumers' => [],
				'rpcClients' => [],
				'rpcServers' => [],
			];

			// only the first connection is autowired
			if (count($this->connectionsMeta) > 1) {
				$connection->setAutowired(FALSE);
			}
		}
	}


	protected function loadProducers($producers)
	{
		$builder = $this->getContainerBuilder();

		foreach ($producers as $name => $config) {
			$config = $this->mergeConfig($config, ['autoSetupFabric' => $builder->parameters[$this->name]['autoSetupFabric']] + $this->producersDefaults);

			if (!isset($this->connectionsMeta[$config['connection']])) {
				throw new Nette\Utils\AssertionException("Connection {$config['connection']} required in producer {$this->name}.{$name} was not defined.");
			}

			$producer = $builder->addDefinition($serviceName = $this->prefix('producer.' . $name))
				->setFactory($config['class'], ['@' . $this->connectionsMeta[$config['connection']]['serviceId']])
				->setType(IProducer::class)
				->addSetup('setContentType', [$config['contentType']])
				->addSetup('setDeliveryMode', [$config['deliveryMode']])
				->addSetup('setRoutingKey', [$config['routingKey']])
				->addTag(self::TAG_PRODUCER);

			if (!empty($config['exchange'])) {
				$config['exchange'] = $this->mergeConfig($config['exchange'], $this->exchangeDefaults);
				Nette\Utils\Validators::assertField($config['exchange'], 'name', 'string:3..', "The config item 'exchange.%' of producer {$this->name}.{$name}");
				Nette\Utils\Validators::assertField($config['exchange'], 'type', 'string:3..', "The config item 'exchange.%' of producer {$this->name}.{$name}");
				$producer->addSetup('setExchangeOptions', [$config['exchange']]);
			}

			$config['queue'] = $this->mergeConfig($config['queue'], $this->queueDefaults);
			$producer->addSetup('setQueueOptions', [$config['queue']]);

			if ($config['autoSetupFabric'] === FALSE) {
				$producer->addSetup('disableAutoSetupFabric');
			}

			$this->connectionsMeta[$config['connection']]['producers'][$name] = $serviceName;
			$this->producersConfig[$name] = $config;
		}
	}


	protected function loadConsumers($consumers)
	{
		$builder = $this->getContainerBuilder();

		foreach ($consumers as $name => $config) {
			$config = $this->mergeConfig($config, ['autoSetupFabric' => $builder->parameters[$this->name]['autoSetupFabric']] + $this->consumersDefaults);
			$config = $this->extendConsumerFromProducer($name, $config);

			if (!isset($this->connectionsMeta[$config['connection']])) {
				throw new Nette\Utils\AssertionException("Connection {$config['connection']} required in consumer {$this->name}.{$name} was not defined.");
			}

			$consumer = $builder->addDefinition($serviceName = $this->prefix('consumer.' . $name))
				->addTag(self::TAG_CONSUMER)
				->setAutowired(FALSE);

			if (!empty($config['exchange'])) {
				Nette\Utils\Validators::assertField($config['exchange'], 'name', 'string:3..', "The config item 'exchange.%' of consumer {$this->name}.{$name}");
				Nette\Utils\Validators::assertField($config['exchange'], 'type', 'string:3..', "The config item 'exchange.%' of consumer {$this->name}.{$name}");
				$consumer->addSetup('setExchangeOptions', [$this->mergeConfig($config['exchange'], $this->exchangeDefaults)]);
			}

			if (!empty($config['queues']) && empty($config['queue'])) {
				foreach ($config['queues'] as $queueName => $queueConfig) {
					$queueConfig['name'] = $queueName;
					$config['queues'][$queueName] = $this->mergeConfig($queueConfig, $this->queueDefaults);

					if (isset($queueConfig['callback'])) {
						$config['queues'][$queueName]['callback'] = self::fixCallback($queueConfig['callback']);
					}
				}

				$consumer
					->setType(MultipleConsumer::class)
					->addSetup('setQueues', [$config['queues']]);

			} elseif (empty($config['queues']) && !empty($config['queue'])) {
				$consumer
					->setType(Consumer::class)
					->addSetup('setQueueOptions', [$this->mergeConfig($config['queue'], $this->queueDefaults)])
					->addSetup('setCallback', [self::fixCallback($config['callback'])]);

			} else {
				$consumer
					->setType(AnonymousConsumer::class)
					->addSetup('setCallback', [self::fixCallback($config['callback'])]);
			}

			$consumer->setArguments(['@' . $this->connectionsMeta[$config['connection']]['serviceId']]);

			if (array_filter($config['qos'])) { // has values
				$config['qos'] = $this->mergeConfig($config['qos'], $this->qosDefaults);
				$consumer->addSetup('setQosOptions', [
					$config['qos']['prefetchSize'],
					$config['qos']['prefetchCount'],
					$config['qos']['global'],
				]);
			}

			if ($config['idleTimeout']) {
				$consumer->addSetup('setIdleTimeout', [$config['idleTimeout']]);
			}

			if ($config['autoSetupFabric'] === FALSE) {
				$consumer->addSetup('disableAutoSetupFabric');
			}

			$this->connectionsMeta[$config['connection']]['consumers'][$name] = $serviceName;
		}
	}


	private function extendConsumerFromProducer(&$consumerName, $config)
	{
		if (isset($config[self::EXTENDS_KEY])) {
			$producerName = $config[self::EXTENDS_KEY];

		} elseif ($m = Nette\Utils\Strings::match($consumerName, '~^(?P<consumerName>[^>\s]+)\s*\<\s*(?P<producerName>[^>\s]+)\z~')) {
			$consumerName = $m['consumerName'];
			$producerName = $m['producerName'];

		} else {
			return $config;
		}

		if (!isset($this->producersConfig[$producerName])) {
			throw new Nette\Utils\AssertionException("Consumer {$this->name}.{$consumerName} cannot extend unknown producer {$this->name}.{$producerName}.");
		}
		$producerConfig = $this->producersConfig[$producerName];

		if (!empty($producerConfig['exchange'])) {
			$config['exchange'] = $this->mergeConfig($config['exchange'], $producerConfig['exchange']);
		}

		if (empty($config['queues']) && !empty($producerConfig['queue'])) {
			$config['queue'] = $this->mergeConfig($config['queue'], $producerConfig['queue']);
		}

		return $config;
	}


	protected function loadRpcClients($clients)
	{
		$builder = $this->getContainerBuilder();

		foreach ($clients as $name => $config) {
			$config = $this->mergeConfig($config, $this->rpcClientDefaults);

			if (!isset($this->connectionsMeta[$config['connection']])) {
				throw new Nette\Utils\AssertionException("Connection {$config['connection']} required in rpc client {$this->name}.{$name} was not defined.");
			}

			$builder->addDefinition($serviceName = $this->prefix('rpcClient.' . $name))
				->setType(RpcClient::class)
				->setFactory(RpcClient::class, ['@' . $this->connectionsMeta[$config['connection']]['serviceId']])
				->addSetup('initClient', [$config['expectSerializedResponse']])
				->addTag(self::TAG_RPC_CLIENT)
				->setAutowired(FALSE);

			$this->connectionsMeta[$config['connection']]['rpcClients'][$name] = $serviceName;
		}
	}


	protected function loadRpcServers($servers)
	{
		$builder = $this->getContainerBuilder();

		foreach ($servers as $name => $config) {
			$config = $this->mergeConfig($config, $this->rpcServerDefaults);

			if (!isset($this->connectionsMeta[$config['connection']])) {
				throw new Nette\Utils\AssertionException("Connection {$config['connection']} required in rpc server {$this->name}.{$name} was not defined.");
			}

			$rpcServer = $builder->addDefinition($serviceName = $this->prefix('rpcServer.' . $name))
				->setType(RpcServer::class)
				->setFactory(RpcServer::class, ['@' . $this->connectionsMeta[$config['connection']]['serviceId']])
				->addSetup('initServer', [$name])
				->addSetup('setCallback', [self::fixCallback($config['callback'])])
				->addTag(self::TAG_RPC_SERVER)
				->setAutowired(FALSE);

			if (array_filter($config['qos'])) { // has values
				$config['qos'] = $this->mergeConfig($config['qos'], $this->qosDefaults);
				$rpcServer->addSetup('setQosOptions', [
					$config['qos']['prefetchSize'],
					$config['qos']['prefetchCount'],
					$config['qos']['global'],
				]);
			}

			$this->connectionsMeta[$config['connection']]['rpcServers'][$name] = $serviceName;
		}
	}


	private function loadConsole(array $config)
	{
		if (!class_exists('Kdyby\Console\DI\ConsoleExtension') || PHP_SAPI !== 'cli') {
			return;
		}

		$builder = $this->getContainerBuilder();

		foreach ([
			         ConsumerCommand::class,
			         PurgeConsumerCommand::class,
			         RpcServerCommand::class,
			         SetupFabricCommand::class,
			         StdInProducerCommand::class,
		         ] as $i => $class) {
			$builder->addDefinition($this->prefix('console.' . $i))
				->setType($class)
				->setArguments([
					'namespace' => $config['consoleNamespace'],
					'alias' => $config['consoleAlias'],
				])
				->addTag(Kdyby\Console\DI\ConsoleExtension::TAG_COMMAND);
		}
	}


	protected function mergeConfig($config, $defaults)
	{
		return static::merge($config, Nette\DI\Helpers::expand($defaults, $this->compiler->getContainerBuilder()->parameters));
	}

	public static function merge($left, $right)
	{
		if (is_array($left) && is_array($right)) {
			foreach ($left as $key => $val) {
				if (is_int($key)) {
					$right[] = $val;
				} else {
					if (is_array($val) && isset($val[self::EXTENDS_KEY])) {
						if ($val[self::EXTENDS_KEY] === self::OVERWRITE) {
							unset($val[self::EXTENDS_KEY]);
						}
					} elseif (isset($right[$key])) {
						$val = static::merge($val, $right[$key]);
					}
					$right[$key] = $val;
				}
			}
			return $right;

		} elseif ($left === null && is_array($right)) {
			return $right;

		} else {
			return $left;
		}
	}


	protected static function fixCallback($callback)
	{
		[$callback] = self::filterArgs($callback);
		if (static::isStatement($callback) && empty($callback->arguments) && substr_count($callback->entity, '::')) {
			$callback = explode('::', $callback->entity, 2);
		}

		return $callback;
	}


	/**
	 * @param string|\stdClass $statement
	 * @return Nette\DI\Statement[]
	 */
	protected static function filterArgs($statement): array
	{
		return Nette\DI\Helpers::filterArguments([is_string($statement) ? static::createStatement($statement) : $statement]);
	}


	public static function register(Nette\Configurator $configurator): void
	{
		$configurator->onCompile[] = static function ($config, Compiler $compiler) {
			$compiler->addExtension('rabbitmq', new RabbitMqExtension());
		};
	}

	protected static function createStatement($class)
	{
		if (class_exists('Nette\DI\Definitions\Statement')) {
			return new Nette\DI\Definitions\Statement($class);
		}
		return new Nette\DI\Statement($class);
	}

	protected static function isStatement($stmt): bool
	{
		return $stmt instanceof Nette\DI\Definitions\Statement || $stmt instanceof Nette\DI\Statement;
	}

}
