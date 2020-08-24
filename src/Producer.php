<?php
declare(strict_types=1);

namespace Kafka;

use Amp\Loop;
use function is_array;
use Kafka\ProducerConfig;
use Kafka\Producer\Process;
use Psr\Log\LoggerAwareTrait;
use Kafka\Producer\SyncProcess;

class Producer
{
    use LoggerAwareTrait;
    use LoggerTrait;

    /**
     * @var Process|SyncProcess
     */
    private $process;

    /**
     * @var \Kafka\ProducerConfig
     */
    protected $config;

    public function __construct(ProducerConfig $config, ?callable $producer = null)
    {
        $this->config = $config;
        $this->process = $producer === null ? new SyncProcess($config) : new Process($config, $producer);
    }

    /**
     * @param mixed[]|bool $data
     *
     * @return mixed[]|null
     *
     * @throws \Kafka\Exception
     */
    public function send($data = true): ?array
    {
        if ($this->logger) {
            $this->process->setLogger($this->logger);
        }

        if (is_array($data)) {
            return $this->sendSynchronously($data);
        }

        $this->sendAsynchronously($data);

        return null;
    }

    /**
     * @param mixed[] $data
     *
     * @return mixed[]
     *
     * @throws \Kafka\Exception
     */
    private function sendSynchronously(array $data): array
    {
        if (! $this->process instanceof SyncProcess) {
            throw new Exception('An asynchronous process is not able to send messages synchronously');
        }

        return $this->process->send($data);
    }

    /**
     * @throws \Kafka\Exception
     */
    private function sendAsynchronously(bool $startLoop): void
    {
        if ($this->process instanceof SyncProcess) {
            throw new Exception('A synchronous process is not able to send messages asynchronously');
        }

        $this->process->start();

        if ($startLoop) {
            Loop::run();
        }
    }

    public function syncMeta(): void
    {
        $this->process->syncMeta();
    }

    public function success(callable $success): void
    {
        if ($this->process instanceof SyncProcess) {
            throw new Exception('Success callback can only be configured for asynchronous process');
        }

        $this->process->setSuccess($success);
    }

    public function error(callable $error): void
    {
        if ($this->process instanceof SyncProcess) {
            throw new Exception('Error callback can only be configured for asynchronous process');
        }

        $this->process->setError($error);
    }
}
