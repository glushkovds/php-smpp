<?php

namespace PhpSmpp\Service;


use PhpSmpp\Client;

abstract class Service
{

    /** @var array */
    protected $hosts;
    protected $login;
    protected $pass;
    protected $bindMode;
    protected $debug = false;
    protected $debugHandler = 'error_log';

    /** @var Client */
    public $client = null;

    /**
     * Service constructor.
     * @param array $hosts example ['123.123.123.123:2777','124.124.124.124']
     * @param string $login
     * @param string $pass
     * @param string $bindMode example PhpSmpp\Client::BIND_MODE_TRANSMITTER
     * @param bool $debug
     */
    public function __construct($hosts, $login, $pass, $bindMode, $debug = false)
    {
        $this->hosts = $hosts;
        $this->login = $login;
        $this->pass = $pass;
        $this->bindMode = $bindMode;
        $this->debug = $debug;
        $this->initClient();
    }

    abstract function bind();

    protected function initClient()
    {
        if (!empty($this->client)) {
            return;
        }
        $this->client = new Client($this->hosts);
        $this->client->debug = $this->debug;
        $this->client->setDebugHandler($this->debugHandler);
    }

    protected function openConnection()
    {
        $this->initClient();
        $this->client->getTransport()->debug = $this->debug;
        $this->client->getTransport()->open();
    }


    public function unbind()
    {
        $this->client->close();
    }

    /**
     * Проверим, если нет коннекта, попытаемся подключиться. Иначе кидаем исключение
     * @throws SocketTransportException
     */
    public function enshureConnection()
    {

        // Когда явно нет подключения: либо ни разу не подключались либо отключились unbind
        if (empty($this->client)) {
            $this->bind();
        }

        // Когда транспорт потерял socket_connect
        if (!$this->client->getTransport()->isOpen()) {
            $this->unbind();
            $this->bind();
        }

        try {
            $this->client->enquireLink();
        } catch (\Throwable $e) {
            $this->unbind();
            $this->bind();
            $this->client->enquireLink();
        }
    }

    public function setDebugHandler(callable $callback)
    {
        $this->debugHandler = $callback;
        if ($this->client) {
            $this->client->setDebugHandler($callback);
        }
    }

}