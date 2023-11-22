# Mallgroup / RabbitMQ

## Content

- [Usage - how use it](#usage)
    - [Extension registration](#extension-registration)
    - [Example configuration](#example-configuration)
    - [Declaring Queues and Exchanges](#declaring-queues-and-exchanges)
    - [Publishing messages](#publishing-messages)
    - [Consuming messages](#consuming-messages)
    - [Running a consumer trough CLI](#running-a-consumer-trough-cli)

## Usage

### Extension registration

config.neon:

```neon
extensions:
	rabbitmq: Bckp\RabbitMQ\DI\RabbitMQExtension
```

### Example configuration

```neon
services:
	- TestConsumer

rabbitmq:
	connections:
		default:
			user: guest
			password: guest
			host: localhost
			port: 5672
			lazy: false
			publishConfirm: true # enables confirm mode on rabbitmq publish (this inform you about ack/nack due policy, this function is experimental)
			heartbeatCallback: [@class, function] # Callback that is called every time real heartbeat is send

	queues:
		testQueue:
			connection: default
			autoCreate: lazy
			  # true - force queue declare on first queue operation during request
			  # lazy - force queue declare as late as possible (when first needed)
			  # false - do not declare (will fail if not created)
			  # never - do not declare even if command is called, this one is mainly for no-permissions state

		autoDlxQueue:
			connection: default
			autoCreate: true
			dlx: [+5min, +15min]
			# Will create automaticly 2 new dlx queues and exchanges to handle them
			# if message is rejected in queue, it will go to first DLX, on second reject it will go to second DLX, on third will be throwed away

	exchanges:
		testExchange:
			connection: default
			type: fanout
			queueBindings:
				testQueue:
					routingKey: testRoutingKey
			autoCreate: lazy
			  # true - force queue declare on first queue operation during request
			  # lazy - force queue declare as late as possible (when first needed)
			  # false - do not declare (will fail if not created)
			  # never - do not declare even if command is called, this one is mainly for no-permissions state

		multipleRoutingKey:
			connection: default
			type: fanout
			queueBindings:
				testQueue:
					routingKey:
						- testRoutingKey
						- testRoutingKey2
						# Multiple routing keys
			autoCreate: lazy

		federatedExchange:
			connection: default
			type: fanout
			queueBindings:
				testQueue:
					routingKey: testRoutingKey
			# this will try connect to upstream rabbitmq (uri) and set federation
			# rabbitmq_federation_management rabbit plugin and php curl extension required
			# this function is still experimental!
			federation:
				uri: amqp://user:pass@host:port
				prefetchCount: 10
				reconnectDelay: 10 # value in seconds, same as rabbitmq
				messageTTL: 3600000 # value in milliseconds, same as rabbitmq
				expires: 3600000
				ackMode: no-ack
				policy: # this is optionally
				    priority: 1 # default is 0, priority of policy to create
				    arguments: # all possible arguments, you can use CamelCase or dashes
				        HASync: all # this will be translated as ha-sync: all
				        ha-sync-mode: automatic

	producers:
		testProducer:
			exchange: testExchange
			# queue: testQueue
			contentType: application/json
			deliveryMode: 2 # Producer::DELIVERY_MODE_PERSISTENT

	consumers:
		testConsumer:
			queue: testQueue
			callback: [@TestConsumer, consume]
			qos:
				prefetchSize: 0
				prefetchCount: 5

# Enable tracy bar panel
tracy:
	bar:
		- Bckp\RabbitMQ\Diagnostics\BarPanel
```

### Declaring Queues and Exchanges

Since v3.0, all queues and exchanges are by default declared on demand using the console command:

```bash
php index.php rabbitmq:declareQueuesAndExchanges
```

It's intended to be a part of the deploy process to make sure all the queues and exchanges are prepared for use.

If you need to override this behavior (for example only declare queues that are used during a request and nothing else),
just add the `autoCreate: true` parameter to queue or exchange of your choice.

You may also want to declare the queues and exchanges via rabbitmq management interface or a script but if you fail to
do so, don't run the declare console command and don't specify `autoCreate: true`, exceptions will be thrown when
accessing undeclared queues/exchanges.

If you need to declare the queues and exchanges as late as possible, you can set `autoCreate: lazy`, that will move creation
on the real use of queues/exchanges, so initializing classes will not trigger creation.

Now, time to time you can have exchange, you want to connect to, but do not have rights to declare it. In that case,
you can use newly added option `autoCreate: never` that will prevent declaration.

### Federation

If your rabbit is capable of Federation, you can easily set federation exchange. Just be aware, you must be able to connect
to upstream server and have correct rights.

For now, only exchanges are supported for federation, but queues will be added in near future.

You can still modify URI of federation link, to add parameters like ?heartbeat=5 to add heartbeat every 5 seconds or ?heartbeat=5&connection_timeout=10000 to have heartbeat 5 seconds and timeout 10 seconds.

if you need more about federation, see https://www.rabbitmq.com/federation.html

### Publishing messages

services.neon:

```neon
services:
	- TestQueue(@Bckp\RabbitMQ\Client::getProducer(testProducer))
```

TestQueue.php:

```php
<?php

declare(strict_types=1);

use Bckp\RabbitMQ\Producer\Producer;

final class TestQueue
{

	/**
	 * @var Producer
	 */
	private $testProducer;


	public function __construct(Producer $testProducer)
	{
		$this->testProducer = $testProducer;
	}


	public function publish(string $message): void
	{
		$json = json_encode(['message' => $message]);
		$headers = [];

		$this->testProducer->publish($json, $headers);
	}

}
```

### Publishing messages in cycle

Bunny does not support well producers that run a long time but send the message only once in a long period. Producers often drop connection in the middle but bunny have no idea about it (stream is closed) and if you try to write some data, an exception will be thrown about broken connection.
Drawback: you must call heartbeat by yourself.
In the example below, you can see that Connection::sendHearbeat() is callen in every single cycle - that is not a problem as internally, `Bckp\RabbitMQ` will actually let you send the heartbeat to rabbitmq only once per 1 second.

LongRunningTestQueue.php:

```php
<?php

declare(strict_types=1);

use Bckp\RabbitMQ\Producer\Producer;

final class LongRunningTestQueue
{

	/**
	 * @var Producer
	 */
	private $testProducer;

	/**
     * @var DataProvider Some data provider
     */
	private $dataProvider;

	/**
     * @var bool
     */
	private $running;


	public function __construct(Producer $testProducer, DataProvider $dataProvider)
	{
		$this->testProducer = $testProducer;
		$this->dataProvider = $dataProvider;
	}

	public function run(): void {
	    do {
	        $message = $this->dataProvider->getMessage();
	        if (!$message) {
	            $this->testProducer->sendHeartbeat();
	            continue;
	        }

	        $this->publish($message);
	    } while ($this->running);
	}


	public function publish(string $message): void
	{
		$json = json_encode(['message' => $message]);
		$headers = [];

		$this->testProducer->publish($json, $headers);
	}

}
```


### Consuming messages

Your consumer callback has to return a confirmation that particular message has been acknowledges (or different states -
unack, reject).

TestConsumer.php

```php
<?php

declare(strict_types=1);

use Bunny\Message;
use Bckp\RabbitMQ\Consumer\IConsumer;

final class TestConsumer implements IConsumer
{

	public function consume(Message $message): int
	{
		$messageData = json_decode($message->content);

		$headers = $message->headers;

		/**
		 * @todo Some logic here...
		 */

		return IConsumer::MESSAGE_ACK; // Or ::MESSAGE_NACK || ::MESSAGE_REJECT
	}

}
```

### Consuming messages in bulk

Sometimes, you want to consume more messages at once, for this purpose, there is BulkConsumer.

TestBulkConsumer.php

```php
<?php

declare(strict_types=1);

use Bunny\Message;
use Bckp\RabbitMQ\Consumer\IConsumer;

final class TestConsumer
{

	/**
	 * @param Message[] $messages
	 * @return array(delivery_tag => MESSAGE_STATUS)
	 */
	public function consume(array $messages): array
	{
		$return = [];
		$data = [];
		foreach($messages as $message) {
			$data[$message->deliveryTag] = json_decode($message->content);
		}

		/**
		 * @todo bulk message action
		 */

		 foreach(array_keys($data) as $tag) {
			$return[$tag] = IConsumer::MESSAGE_ACK; // Or ::MESSAGE_NACK || ::MESSAGE_REJECT
		 }

		return $return;
	}

}
```


### Running a consumer trough CLI

There are two consumer commands prepared. `rabbitmq:consumer` wiil consume messages for specified amount of time (in
seconds), to run indefinitely skip this parameter. Following command will be consuming messages for one hour:

```bash
php index.php rabbitmq:consumer testConsumer 3600
```

Following command will be consuming messages indefinitely:

```bash
php index.php rabbitmq:consumer testConsumer
```

`rabbitmq:staticConsumer` will consume particular amount of messages. Following example will consume just 20 messages:

```bash
php index.php rabbitmq:staticConsumer testConsumer 20
```
