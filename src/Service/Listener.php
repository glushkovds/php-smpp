<?php

namespace PhpSmpp\Service;


class Listener extends Service
{

    public function bind()
    {
        $this->openConnection();
        $this->client->bindReceiver($this->login, $this->pass);
    }

    public function listen(Callable $callback)
    {
        while (true) {
            $this->listenOnce($callback);
            usleep(10e4);
        }
    }

    public function listenOnce(Callable $callback)
    {
        $this->enshureConnection();
        $this->client->listenSm($callback);
    }

}