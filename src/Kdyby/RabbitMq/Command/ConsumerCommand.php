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
		$this->setName(self::NAME);
		if ($alias = $this->computeAlias(self::NAME)) {
			$this->setAliases([$alias]);
		}
		$this->setDescription('Starts a configured consumer');
	}

}
