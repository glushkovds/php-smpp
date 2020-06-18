<?php

namespace PhpSmpp\Service;


use PhpSmpp\Client;

abstract class Service
{

    /** @var array */
    protected $hosts;
    protected $login;
    protected $pass;
    protected $debug = false;
    protected $debugHandler = 'error_log';

    /** @var Client */
    public $client = null;

    public function __construct($hosts, $login, $pass, $debug = false)
    {
        $this->hosts = $hosts;
        $this->login = $login;
        $this->pass = $pass;
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