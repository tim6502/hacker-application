<?php

require('instance.cgi');

require('runez/runez.php');

siteInit(true, true);


extract(runezCheckGame());


$start_event_id = filter($_REQUEST['start_event_id'], 0);

$event_list = $runez->getEvents($start_event_id);
if ($event_list === false) {
	siteReturnData($runez->errorMessage(), $format);
	exit();
}


siteReturnData(array('event_list' => $event_list), $format);


