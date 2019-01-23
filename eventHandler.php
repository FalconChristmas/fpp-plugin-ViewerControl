<?php

require_once __DIR__.'/vendor/autoload.php';

/**
 * example callback function.
 *
 * @param SseClient\Event $event
 */
function someCallbackFunction(\SseClient\Event $event)
{
    print_r($event);

	printf( "data           : '%s'\n", $event->getData());
	printf( "eventType      : '%s'\n", $event->getEventType());
	printf( "id             : '%s'\n", $event->getId());
}

// if authentication needed - add to url auth parameter ?auth=CREDENTIAL
// where "CREDENTIAL" can either be your Firebase Secret or an authentication token.
$client = new SseClient\Client('http://www.ControlMyLights.com/events.php');

// returns generator
$events = $client->getEvents();

// blocks until new event arrive
foreach ($events as $event) {
    // pass event to callback function
    someCallbackFunction($event);
}

?>
