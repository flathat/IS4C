<?php
include(dirname(__FILE__) . '/../../../../../config.php');
$memNum = isset($_REQUEST['memNum'])?$_REQUEST['memNum']:'';
$programID = isset($_REQUEST['programID'])?$_REQUEST['programID']:'';
$date1 = isset($_REQUEST['date1'])?$_REQUEST['date1']:'';
$date2 = isset($_REQUEST['date2'])?$_REQUEST['date2']:'';
$location = sprintf('Location: ActivityReport.php?memNum=%s&programID=%s&date1=%s&date2=%s'
    ,$memNum
    ,$programID
    ,$date1
    ,$date2
                );
header("$location");
exit;

