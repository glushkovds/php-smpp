<?php

namespace PhpSmpp\Service;


class Listener extends Service
{

    public function bind()
    {
        $this->openConnection();
        $this->client->bindReceiver($this->login, $this->password);
    }

    public function listen(Callable $callback)
    {
        while (true) {
            $this->enshureConnection();
            $this->client->listenSm($callback);
            usleep(10e4);
        }
    }

}