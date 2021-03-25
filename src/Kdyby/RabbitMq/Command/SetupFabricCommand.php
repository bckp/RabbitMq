<?php

namespace Kdyby\RabbitMq\Command;

use Kdyby\RabbitMq\AmqpMember;
use Kdyby\RabbitMq\DI\RabbitMqExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;



/**
 * @author Alvaro Videla <videlalvaro@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class SetupFabricCommand extends Command
{
	public const NAME = 'setup-fabric';

	/**
	 * @inject
	 * @var \Nette\DI\Container
	 */
	public $container;

	protected function configure()
	{
		$this
			->setName('kdybyrabbitmq:' . self::NAME)
			->setAliases(['rabbitmq:' . self::NAME])
			->setDescription('Sets up the Rabbit MQ fabric')
			->addOption('debug', 'd', InputOption::VALUE_NONE, 'Enable Debugging');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if (defined('AMQP_DEBUG') === false) {
			define('AMQP_DEBUG', (bool) $input->getOption('debug'));
		}

		$output->writeln('Setting up the Rabbit MQ fabric');

		foreach ([
			RabbitMqExtension::TAG_PRODUCER,
			RabbitMqExtension::TAG_CONSUMER,
			RabbitMqExtension::TAG_RPC_CLIENT,
			RabbitMqExtension::TAG_RPC_SERVER
		] as $tag) {
			foreach ($this->container->findByTag($tag) as $serviceId => $meta) {
				/** @var AmqpMember $service */
				$service = $this->container->getService($serviceId);
				$service->setupFabric();
			}
		}
	}

}
