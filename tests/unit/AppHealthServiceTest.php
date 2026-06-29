<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Admin\Services\AppHealthService;
use PHPUnit\Framework\TestCase;

class AppHealthServiceTest extends TestCase
{
    public function testAvailableWhenTcpPortAcceptsConnections(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($server)) {
            $this->markTestSkipped('Local TCP server is not available in this environment: ' . $errorMessage);
        }

        try {
            $address = (string)stream_socket_get_name($server, false);
            $port = (int)substr(strrchr($address, ':'), 1);

            $service = new AppHealthService('127.0.0.1', $port, 0.2);

            $this->assertTrue($service->isAvailable());
        } finally {
            fclose($server);
        }
    }

    public function testUnavailableWhenHostOrPortIsInvalid(): void
    {
        $this->assertFalse((new AppHealthService('', 9000))->isAvailable());
        $this->assertFalse((new AppHealthService('127.0.0.1', 0))->isAvailable());
    }
}
