<?php

namespace RatchetApp;
use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

class Pusher implements WampServerInterface {
    /**
     * A lookup of all the topics clients have subscribed to
     */
    public $subscribedTopics = array();
    protected $redis;

    public function init($client) {
        $this->redis = $client;
        $this->log("Connected to Redis, now listening for incoming messages...");
    }

    public function log($value)
    {
        echo sprintf("Pusher: %s\n", $value);
    }

    public function onSubscribe(ConnectionInterface $conn, $topic) {
        $this->log("onSubscribe");
        $this->log("topic: $topic {$topic->count()}");
        // When a visitor subscribes to a topic link the Topic object in a  lookup array
        if (!array_key_exists($topic->getId(), $this->subscribedTopics)) {
            $this->subscribedTopics[$topic->getId()] = $topic;
            $pubsubContext = $this->redis->pubsub($topic->getId(), array($this, 'pubsub'));
            $this->log("subscribed to topic $topic");
        }
    }

    /**
     * @param string
     */
    public function pubsub($event, $pubsub) {
        $this->log("pubsub");
        $this->log("kind: $event->kind channel: $event->channel payload: $event->payload");

        if (!array_key_exists($event->channel, $this->subscribedTopics)) {
            $this->log("no subscribers, no broadcast");
            return;
        }

        $topic = $this->subscribedTopics[$event->channel];
        $this->log("$event->channel: $event->payload {$topic->count()}");
        $topic->broadcast("$event->channel: $event->payload");

        // quit if we get the message from redis
        if (strtolower(trim($event->payload)) === 'quit') {
            $this->log("quitting...");
            $pubsub->quit();
        }
    }

    public function onUnSubscribe(ConnectionInterface $conn, $topic) {
        $this->log("onUnSubscribe");
        $this->log("topic: $topic {$topic->count()}");
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->log("onOpen");
    }

    public function onClose(ConnectionInterface $conn) {
        $this->log("onClose");
    }

    public function onCall(ConnectionInterface $conn, $id, $topic, array $params) {
        // In this application if clients send data it's because the user hacked around in console
        $this->log("onCall");
        $conn->callError($id, $topic, 'You are not allowed to make calls')->close();
    }

    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible) {
        // In this application if clients send data it's because the user hacked around in console
        $this->log("onPublish");
        $topic->broadcast("$topic: $event");
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->log("onError");
    }
}