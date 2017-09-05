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
 * NOTE: SQLManager's transfer method is not the fastest way of pulling
 * this off. I'm using it so I can mix & match MySQL and SQL Server
 * without errors.
 *
 * Rewriting the loop to use mysql commandline programs would be good
 * if everything's on the same dbms. Using the global settings in
 * $FANNIE_MIRRORS is the important part. Rough sketch of this
 * is in comments below.
 * Using fannie/sync/special_mirror/* is one way to effect this.
 *
 */
include('../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class TableSyncMirrorPage extends FanniePage
{

	protected $title = "Fannie : Mirror Data";
	protected $header = "Mirroring data";

	private $errors = array();
	private $results = '';
	private $specials_only = true;

	function preprocess() {	
		global $FANNIE_OP_DB, $FANNIE_TRANS_DB, $FANNIE_ARCHIVE_DB, $FANNIE_MIRRORS;
		$table = FormLib::get_form_value('tablename','');
		$othertable = FormLib::get_form_value('othertable','');

		if ($table === '' && $othertable !== '')
			$table = $othertable;

		if (empty($table)){
			$this->errors[] = "Error: no table was specified";
			return True;
		}
		elseif (ereg("[^A-Za-z0-9_]",$table)){
			$this->errors[] = "Error: \"$table\" contains illegal characters";
			return True;
		}

		$dbc = FannieDB::get($FANNIE_OP_DB);
		// The name of the local db.
		$local_db = '';
		// Index of the element in $mirror naming the db.
		$mirror_db = '';
		if ($dbc->table_exists($table)) {
			$local_db = $FANNIE_OP_DB;
			$mirror_db = 'op';
		} else {
			$dbc = FannieDB::get($FANNIE_TRANS_DB);
			if ($dbc->table_exists($table)) {
				$local_db = $FANNIE_TRANS_DB;
				$mirror_db = 'trans';
			} else {
				$dbc = FannieDB::get($FANNIE_ARCHIVE_DB);
				if ($dbc->table_exists($table)) {
					$local_db = $FANNIE_ARCHIVE_DB;
					$mirror_db = 'arch';
				} else {
					$this->errors[] = "Error: Cannot find \"$table\"";
					return True;
				}
			}
		}

/*
$this->errors[] = "OK: Found \"$table\" in \"$local_db\"  destination index \"$mirror_db\"";
return True;
*/
        $this->results = "<p style='font-family:Arial; font-size:1.0em;'>" .
                    "Mirroring table $table ".
                    "<ul>";

		if (file_exists("special_mirror/$table.php")){
			ob_start();
			include("special_mirror/$table.php");
			// (The included code is executed.)
			$this->results .= ob_get_clean();
		} else {
            if ($this->specials_only) {
                $this->errors[] = "Oops!: No special_mirror/{$table}.php for $table";
                $this->results .= "</ul></p>";
                return True;
            }
			$i = 1;
			foreach ($FANNIE_MIRRORS as $mirror){
				// Connect to the mirror.
				$dbc->add_connection($mirror['host'],$mirror['type'],
					$mirror["$mirror_db"],$mirror['user'],$mirror['pw']);
				// If connection to the mirror succeeded.
				if ($dbc->connections[$mirror["$mirror_db"]]) {
					// Empty the mirror table.
					$dbc->query("TRUNCATE TABLE $table",$mirror["$mirror_db"]);
					//  Local operation, mirror operation.
					$success = $dbc->transfer($local_db,
											 "SELECT * FROM $table",
										 $mirror["$mirror_db"],
											 "INSERT INTO $table");
					$dbc->close($mirror["$mirror_db"]);
					if ($success){
                        $this->results .= "<li>Mirror ".$i.
                            " ({$mirror['host']}) completed successfully</li>";
					}
					else {
						$this->errors[] = "Mirror ".$i.
                            " ({$mirror['host']}) completed but with some errors";
					}
				}
				else {
					$this->errors[] = "Mirror ".$i.
                        " ({$mirror['host']}) couldn't connect to mirror";
				}
				$i++;
			}
		}

		$this->results .= "</ul></p>";
		$this->results .= "<a href='javascript:history.back();'>Back (for another)</a>";
		$this->results .= "</ul></p>";
		
		return True;

	// preprocess()
	}

	function body_content(){
		$ret = '';
		if (count($this->errors) > 0){
			$ret .= '<blockquote style="border: solid 1px red; padding: 4px;"><ul>';	
			foreach($this->errors as $e)
				$ret .= '<li>'.$e.'</li>';	
			$ret .= '</ul><a href="SyncMirrorIndexPage.php">Try Again</a></blockquote>';
		}
		$ret .= $this->results;
		return $ret;
	}

    public function helpContent()
    {
        return '<p>The results page of send data from the source server to a target server.
            </p>
            ';
    }

// class TableSyncMirrorPage
}

/*
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])){
	$obj = new TableSyncMirrorPage();
	$obj->draw_page();
}
*/

FannieDispatch::conditionalExec(false);

?>
