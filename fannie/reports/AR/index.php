<?php
/* 26Mar2014 The pre-class version, probably obsolete
if (isset($_REQUEST['memNum'])) {
    header('Location: ArReport.php?memNum='.$_REQUEST['memNum']);
} else {
    header('Location: ArReport.php');
}

<<<<<<< HEAD
include('../../config.php');
include($FANNIE_ROOT.'src/trans_connect.php');
=======
include(dirname(__FILE__) . '/../../config.php');
>>>>>>> origin/master
*/

$memNum = isset($_REQUEST['memNum'])?(int)$_REQUEST['memNum']:'';
$date1 = isset($_REQUEST['date1'])?$_REQUEST['date1']:'';
$date2 = isset($_REQUEST['date2'])?$_REQUEST['date2']:'';
//header('Location: ArReport.php?memNum='.$memNum);
$location = sprintf('Location: ArWithDatesReport.php?memNum=%d&date1=%s&date2=',
    $memNum, $date1, $date2);
header($location);

?>
