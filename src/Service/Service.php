<?php

namespace PhpSmpp\Service;


use PhpSmpp\SMPP\SmppClient;

abstract class Service
{

    /** @var array */
    protected $hosts;
    protected $login;
    protected $pass;
    protected $debug = false;

    /** @var SmppClient */
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
        $this->client = new SmppClient($this->hosts);
        $this->client->debug = $this->debug;
    }

    protected function openConnection()
    {
        $this->client->getTransport()->debug = $this->debug;
        $this->client->getTransport()->open();
    }


    public function unbind()
    {
        $this->client = null;
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

}