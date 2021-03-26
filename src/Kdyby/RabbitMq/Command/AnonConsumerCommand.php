<?php

namespace Kdyby\RabbitMq\Command;

use Kdyby\RabbitMq\AnonymousConsumer;
use Kdyby\RabbitMq\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;



/**
 * @author Alvaro Videla <videlalvaro@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class AnonConsumerCommand extends BaseConsumerCommand
{
	public const NAME = 'anon-consumer';

	protected function configure()
	{
		parent::configure();

		$this->setName(self::NAME);
		$this->setDescription('Starts an anonymouse configured consumer');

		$this->getDefinition()->getOption('messages')->setDefault(1);
		$this->getDefinition()->getOption('route')->setDefault('#');

		if ($alias = $this->computeAlias(self::NAME)) {
			$this->setAliases([$alias]);
		}
	}



	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		parent::initialize($input, $output);

		if (!$this->consumer instanceof AnonymousConsumer) {
			throw new InvalidArgumentException(
				'Expected instance of Kdyby\RabbitMq\AnonymousConsumer, ' .
				'but consumer ' . $input->getArgument('name'). ' is ' . get_class($this->consumer)
			);
		}
	}

}
