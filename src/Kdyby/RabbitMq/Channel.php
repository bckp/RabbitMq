<?php

namespace Kdyby\RabbitMq;

use PhpAmqpLib;



/**
 * @author Marc Weistroff <marc.weistroff@sensio.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class Channel extends PhpAmqpLib\Channel\AMQPChannel
{

	/**
	 * @var Diagnostics\Panel
	 */
	private $panel;



	public function injectPanel(Diagnostics\Panel $panel): void
	{
		$this->panel = $panel;
	}



	public function basic_publish($msg, $exchange = '', $routing_key = '', $mandatory = false, $immediate = false, $ticket = NULL)
	{
		if ($this->panel) {
			$this->panel->published(get_defined_vars()); // all args
		}

		parent::basic_publish($msg, $exchange, $routing_key, $mandatory, $immediate, $ticket);
	}

}
