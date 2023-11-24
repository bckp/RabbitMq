<?php

declare(strict_types=1);

namespace Bckp\RabbitMQ\Connection\Exception;

use Bunny\Exception\ClientException;

class WaitTimeoutException extends ClientException
{
}
