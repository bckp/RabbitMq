<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\Tests\Cases;

use Bckp\RabbitMQ\Connection\ConnectionFactory;
use Bckp\RabbitMQ\Connection\IConnection;
use Bckp\RabbitMQ\Exchange\ExchangeDeclarator;
use Bckp\RabbitMQ\Exchange\ExchangesDataBag;
use Bckp\RabbitMQ\Exchange\IExchange;
use Bckp\RabbitMQ\LazyDeclarator;
use Bckp\RabbitMQ\Producer\Producer;
use Bckp\RabbitMQ\Queue\IQueue;
use Bckp\RabbitMQ\Queue\QueueDeclarator;
use Bckp\RabbitMQ\Queue\QueuesDataBag;
use Bckp\RabbitMQ\Tests\Mocks\ChannelMock;
use Bckp\RabbitMQ\Tests\Mocks\Helper\RabbitMQMessageHelper;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

final class ProducerTest extends TestCase
{

	public function testQueue(): void
	{
		$testQueueName = 'testQueue';

		$producer = $this->createQueueProducer($testQueueName);

		$messageHelper = RabbitMQMessageHelper::getInstance();
		$messageHelper->reinit();

		$producer->publish('test');

		$testQueueMessages = $messageHelper->getQueueMessages($testQueueName);

		Assert::same(
			[
				[
					'body' => 'test',
					'headers' => [
						'content-type' => 'application/json',
						'delivery-mode' => 2,
					],
				],
			],
			$testQueueMessages
		);
	}


	public function testDirectExchange(): void
	{
		$exchangeName = 'testDirectExchange';
		$producer = $this->createExchangeProducer($exchangeName);
		$messageHelper = RabbitMQMessageHelper::getInstance();
		$messageHelper->reinit();

		$routingKey = 'non-existent-routing-key';
		$producer->publish('should not appear anywhere', [], $routingKey);

		Assert::same([], $messageHelper->getQueueMessages());

		/**************************************************************************************************************/

		$routingKey = 'test-queue-direct-exchange';
		$producer->publish('should appear in one queue', [], $routingKey);

		Assert::same(
			[
				'testQueue' => [
					[
						'body' => 'should appear in one queue',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
			],
			$messageHelper->getQueueMessages()
		);

		/**************************************************************************************************************/

		$routingKey = 'test-queue-direct-exchange';
		$producer->publish('should appear in one queue 2', [], $routingKey);

		Assert::same(
			[
				'testQueue' => [
					[
						'body' => 'should appear in one queue',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
					[
						'body' => 'should appear in one queue 2',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
			],
			$messageHelper->getQueueMessages()
		);

		/**************************************************************************************************************/

		$routingKey = 'non-existent-routing-key';
		$producer->publish('should not appear anywhere', [], $routingKey);

		Assert::same(
			[
				'testQueue' => [
					[
						'body' => 'should appear in one queue',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
					[
						'body' => 'should appear in one queue 2',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
			],
			$messageHelper->getQueueMessages()
		);

		/**************************************************************************************************************/

		$routingKey = 'test-queue-direct-routing-key1';
		$producer->publish('should appear in 2 queues', [], $routingKey);

		Assert::same(
			[
				'testQueue' => [
					[
						'body' => 'should appear in one queue',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
					[
						'body' => 'should appear in one queue 2',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
				'testQueueRK1' => [
					[
						'body' => 'should appear in 2 queues',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
				'testQueueRK2' => [
					[
						'body' => 'should appear in 2 queues',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
			],
			$messageHelper->getQueueMessages()
		);
	}


	public function testFanoutExchange(): void
	{
		$exchangeName = 'testFanoutExchange';
		$producer = $this->createExchangeProducer($exchangeName);
		$messageHelper = RabbitMQMessageHelper::getInstance();
		$messageHelper->reinit();

		$routingKey = 'non-existent-routing-key';
		$producer->publish('should appear everywhere', [], $routingKey);

		Assert::same(
			[
				'testQueue' => [
					[
						'body' => 'should appear everywhere',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
				'testQueueRK1' => [
					[
						'body' => 'should appear everywhere',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
				'testQueueRK2' => [
					[
						'body' => 'should appear everywhere',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
				],
			],
			$messageHelper->getQueueMessages()
		);

		$routingKey = '';
		$producer->publish(
			'should appear everywhere 2',
			[
				'extra-header' => 1,
			],
			$routingKey
		);

		Assert::same(
			[
				'testQueue' => [
					[
						'body' => 'should appear everywhere',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
					[
						'body' => 'should appear everywhere 2',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
							'extra-header' => 1,
						],
					],
				],
				'testQueueRK1' => [
					[
						'body' => 'should appear everywhere',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
					[
						'body' => 'should appear everywhere 2',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
							'extra-header' => 1,
						],
					],
				],
				'testQueueRK2' => [
					[
						'body' => 'should appear everywhere',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
						],
					],
					[
						'body' => 'should appear everywhere 2',
						'headers' => [
							'content-type' => 'application/json',
							'delivery-mode' => 2,
							'extra-header' => 1,
						],
					],
				],
			],
			$messageHelper->getQueueMessages()
		);
	}


	private function createQueueProducer(string $testQueueName): Producer
	{
		$channelMock = new ChannelMock();

		$connectionMock = \Mockery::mock(IConnection::class)
		                          ->shouldReceive('getChannel')->andReturn($channelMock)->getMock()
		                          ->shouldReceive('isPublishConfirm')->andReturnFalse()->getMock();

		$queueMock = \Mockery::mock(IQueue::class)
		                     ->shouldReceive('getConnection')->andReturn($connectionMock)->getMock()
		                     ->shouldReceive('getName')->andReturn($testQueueName)->getMock();

		$producer = new Producer(
			null,
			$queueMock,
			'application/json',
			2,
			$this->createLazyDeclarator()
		);

		return $producer;
	}


	private function createExchangeProducer(string $testExchange): Producer
	{
		$channelMock = new ChannelMock();

		$connectionMock = \Mockery::mock(IConnection::class)
		                          ->shouldReceive('getChannel')->andReturn($channelMock)->getMock()
		                          ->shouldReceive('isPublishConfirm')->andReturnFalse()->getMock();

		$exchangeMock = \Mockery::mock(IExchange::class)
		                        ->shouldReceive('getConnection')->andReturn($connectionMock)->getMock()
		                        ->shouldReceive('getName')->andReturn($testExchange)->getMock();

		$producer = new Producer(
			$exchangeMock,
			null,
			'application/json',
			2,
			$this->createLazyDeclarator()
		);

		return $producer;
	}


	protected function createLazyDeclarator(): LazyDeclarator
	{
		return new LazyDeclarator(
			\Mockery::spy(QueuesDataBag::class),
			\Mockery::spy(ExchangesDataBag::class),
			\Mockery::spy(QueueDeclarator::class),
			\Mockery::spy(ExchangeDeclarator::class),
			\Mockery::spy(ConnectionFactory::class),
		);
	}
}

(new ProducerTest())->run();
