<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op
    Copyright 2013 West End Food Co-op, Toronto

    This file is part of Fannie.

    Fannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Fannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

/*
   If all machines use MySQL, this, which uses mysqldump,
   is much faster than SQLManager transfer.
*/

if (!isset($FANNIE_ROOT))
	include(dirname(__FILE__).'/../../config.php');
include_once($FANNIE_ROOT.'src/tmp_dir.php');

$ret = 0;
$output = array();
if (isset($outputFormat) && $outputFormat == 'plain') {
	$itemStart = '';
	$itemEnd = '';
	$lineBreak = "\n";
}
else {
	$outputFormat = 'html';
	$itemStart = '<li>';
	$itemEnd = '</li>';
	$lineBreak = '<br />';
}

if (empty($table)) {
	echo "{$itemStart}No table named. Cannot run.{$itemEnd}";
	return;
}
$tempfile = tempnam(sys_get_temp_dir(),$table.".sql");

// Make a mysqldump of the table.
exec("mysqldump -u $FANNIE_SERVER_USER -p$FANNIE_SERVER_PW -h $FANNIE_SERVER $local_db $table > $tempfile", $output, $ret);
if ( $ret > 0 ) {
	$report = implode("$lineBreak", $output);
	if ( strlen($report) > 0 )
		$report = "{$lineBreak}$report";
	echo "{$itemStart}mysqldump failed, returned: $ret {$report}{$itemEnd}";
}
else {
	// Load the mysqldump from Fannie to each mirror.
	$mirrorNumber=1;
	foreach($FANNIE_MIRRORS as $mirror){
		$ret = 0;
		$output = array();
		if ( strpos($mirror['host'], ':') > 0 ) {
			list($host, $port) = explode(":", $mirror['host']);
			exec("mysql -u {$mirror['user']} -p{$mirror['pw']} -h {$host} -P {$port} {$mirror["$mirror_db"]} < $tempfile", $output, $ret);
		} else {
			exec("mysql -u {$mirror['user']} -p{$mirror['pw']} -h {$mirror['host']} {$mirror["$mirror_db"]} < $tempfile", $output, $ret);
		}
		if ( $ret == 0 ) {
			echo "{$itemStart}Mirror $mirrorNumber ({$mirror['host']}) $table completed successfully{$itemEnd}";
		} else {
			$report = implode("$lineBreak", $output);
			if ( strlen($report) > 0 )
				$report = "{$lineBreak}$report";
			echo "{$itemStart}Mirror $mirrorNumber ({$mirror['host']}) $table failed, returned: $ret {$report}{$itemEnd}";
		}
		unset($output);
		$mirrorNumber++;
	// each mirror
	}

// mysqldump ok
}

unlink($tempfile);

?>
