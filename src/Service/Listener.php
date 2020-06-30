<?php

namespace PhpSmpp\Service;


use PhpSmpp\Client;

class Listener extends Service
{

    public function bind()
    {
        $this->openConnection();
        if (Client::BIND_MODE_TRANSCEIVER == $this->bindMode) {
            $this->client->bindTransceiver($this->login, $this->pass);
        } else {
            $this->client->bindReceiver($this->login, $this->pass);
        }
    }

    /**
     * @param callable $callback \PhpSmpp\Pdu\Pdu passed as a parameter
     */
    public function listen(callable $callback)
    {
        while (true) {
            $this->listenOnce($callback);
            usleep(10e4);
        }
    }

    public function listenOnce(callable $callback)
    {
        $this->enshureConnection();
        $this->client->listenSm($callback);
    }

}