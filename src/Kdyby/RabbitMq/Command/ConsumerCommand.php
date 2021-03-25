<?php

namespace Kdyby\RabbitMq\Command;



/**
 * @author Alvaro Videla <videlalvaro@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class ConsumerCommand extends BaseConsumerCommand
{
	public const NAME = 'consumer';

	protected function configure()
	{
		parent::configure();

		$this->setName('kdybyrabbitmq:' . self::NAME);
		$this->setAliases(['rabbitmq:' . self::NAME]);
		$this->setDescription('Starts a configured consumer');
	}

}
