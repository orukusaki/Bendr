<?php

namespace AppBundle\Listener;

use Orukusaki\Bundle\SlackBundle\Client;
use Orukusaki\Bundle\SlackBundle\Event\MessageReceivedEvent;
use Psr\Log\LoggerInterface;
use RuntimeException;

class GifShrinkingListener
{
    const MIN_SIZE = 10000000;
    const BASE_URL= 'http://upload.gfycat.com/transcode';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $excludedDomains = ['gfycat.com', 'gifyoutube.com'];

    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function handleMessageEvent(MessageReceivedEvent $event)
    {
        $message = $event->getMessage();
        $this->logger->debug("Message in channel {$message['channel_name']}");

        try {
            $this->shrinkThatGif($message, $event->getChannel());

        } catch (RuntimeException $e) {
            $this->logger->info($e->getMessage());
        }
    }

    /**
     * @param $message
     * @param $channel
     */
    private function shrinkThatGif($message, $channel)
    {
        $originalUrl = $this->findGifUrl($message);

        $this->checkForExcludedDomain($originalUrl);

        $originalSize = $this->getFileSize($originalUrl);

        $this->checkMinimumSize($originalSize);

        $transcodeData = $this->transcode($originalUrl);

        $newSize = number_format($transcodeData['gfysize'] / (1 << 20), 2) . 'MB';

        $url = 'http://gfycat.com/' . $transcodeData['gfyName'];

        $this->client->postMessage(
            [
                'channel'  => $channel,
                'text'     => "Smaller version: <$url> ($newSize)",
                'username' => 'The Almighty Gif Shrinking Bot',
            ]
        );
    }

    /**
     * @param $message
     *
     * @return string
     */
    private function findGifUrl($message)
    {
        if (!preg_match('/<([^>]+\.gif)>/', $message['text'], $matches)) {
            throw new RuntimeException('No gif found');
        }

        $this->logger->debug("Found url {$matches[1]}");

        return $matches[1];
    }

    /**
     * @param $url
     */
    private function checkForExcludedDomain($url)
    {
        foreach ($this->excludedDomains as $excludedDomain) {

            if (strstr($url, $excludedDomain) !== false) {
                throw new RuntimeException("Excluded domain $excludedDomain found");
            }
        }
    }

    /**
     * @param $url
     *
     * @return int
     */
    private function getFileSize($url)
    {
        $headers = get_headers($url);

        $size = 0;
        foreach ($headers as $header) {
            if (preg_match('/Content-Length: (\d+)/', $header, $matches)) {
                $size = intval($matches[1]);
                break;
            }
        }

        $this->logger->debug("Reported size is $size");

        return $size;
    }

    /**
     * @param $fetchUrl
     *
     * @return mixed
     */
    private function transcode($fetchUrl)
    {
        $url = self::BASE_URL . '?' . http_build_query(['fetchUrl' => $fetchUrl]);

        return $this->checkTranscodeResponse(file_get_contents($url));
    }

    /**
     * @param $originalSize
     */
    private function checkMinimumSize($originalSize)
    {
        if ($originalSize < self::MIN_SIZE) {
            throw new RuntimeException('Too small');
        }
    }

    private function checkTranscodeResponse($response)
    {
        $this->logger->info($response);

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new RuntimeException("json decode failed");
        }

        if (!isset($data['gfysize']) || !$data['gfysize']) {
            throw new RuntimeException("invalid size returned");
        }

        if (!isset($data['gfyName']) || !$data['gfyName']) {
            throw new RuntimeException("no gfyName field");
        }

        return $data;
    }
}
