<?php
namespace AppBundle\Listener;

class BurritoListener
{
    protected $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function handleMessageEvent($event)
    {
        $command = $event->getCommand();
        if (strpos($command['command'], '/burritos') !== false) {
            $this->client->postMessage(
                [
                    'channel' => $command['channel_id'],
                    'text'    => ':fire: :burrito: Burritos! <http://invi.qa/sheffield-burrito> :burrito: :fire:',
                ]
            );
        }
    }
}
