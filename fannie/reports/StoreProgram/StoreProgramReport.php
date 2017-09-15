<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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
/* --COMMENTS - - -
 * 22May14 Include today's data if requested.
 *         -> Technique must change when dlog formats change.
 *         New core_trans.dlog seems to be same as trans_archive.dlog*
 * 25Feb14 Drill down to member detail.
 * 24Feb14 This only goes to previous day.  How to do today?
 */

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

/* 22May14 After the impending upgrade select_dlog.php
include('../../config.php');
include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
include($FANNIE_ROOT.'src/select_dlog.php');
 * nor any of the below will be needed.
include($FANNIE_ROOT.'src/mysql_connect.php');
include($FANNIE_ROOT.'classlib2.0/FannieReportPage.php');
include($FANNIE_ROOT.'classlib2.0/lib/FormLib.php');
*/

class StoreProgramReport extends FannieReportPage {
    public $description = '[Store Program Report] Inputs and Transfers in Store Program Accounts
        (where an amount is paid ahead for the member, the account has a credit balance).
        For both members and the Program Bank';
    public $report_set = 'Other Reports';
    public $themed = false;

	function preprocess(){
		global $FANNIE_WINDOW_DRESSING;
		/**
		  Set the page header and title, enable caching
		*/
		$this->title = "WEFC Store Program : Program Events: Inputs and Transfers Report";
		$this->header = "WEFC Store Program : Program Events: Inputs and Transfers Report";
		$this->report_cache = 'none';

		if (isset($_REQUEST['date1'])){
			/**
			  Form submission occurred

			  Change content function, turn off the menus,
			  set up headers
			*/
			$this->content_function = "report_content";
			if ( isset($FANNIE_WINDOW_DRESSING) && $FANNIE_WINDOW_DRESSING == True )
				$this->has_menus(True);
			else
				$this->has_menus(False);

			$this->report_headers = array('Date','When','Member#','Member Name','Event','$ Amount');
		
			/**
			  Check if a non-html format has been requested
			*/
			if (isset($_REQUEST['excel']) && $_REQUEST['excel'] == 'xls') {
				$this->report_format = 'xls';
				$this->has_menus(False);
        } elseif (isset($_REQUEST['excel']) && $_REQUEST['excel'] == 'csv') {
				$this->report_format = 'csv';
				$this->has_menus(False);
            }
		}
		else 
			$this->add_script("../../src/CalendarControl.js");

		return True;
	}

	function fetch_report_data(){
		global $dbc, $FANNIE_ARCHIVE_DB, $FANNIE_OP_DB;
		$date1 = FormLib::get_form_value('date1',date('Y-m-d'),'');
		$date2 = FormLib::get_form_value('date2',date('Y-m-d'),'');
		$card_no = FormLib::get_form_value('card_no','0');

        $dbc = FannieDB::get($FANNIE_ARCHIVE_DB);

        // date1='' is epoch. From program table.
        // get in preprocess
        $programStartDate = '2014-01-01';
        $date1 = (($date1 == '')?$programStartDate:$date1);
        // date2='' is now
        $date2 = (($date2 == '')?date('Y-m-d'):$date2);
	
		//$dlog = select_dlog($date1,$date2);
        $dlog = DTransactionsModel::selectDlog($date1,$date2);
//(select * from trans_archive.dlog201401 union all select * from trans_archive.dlog201402 union all select * from trans_archive.dlog201403 union all select * from trans_archive.dlog201404 union all select * from trans_archive.dlog201405)
        // 22May2014 trans_archive.dlog* and core_trans.dlog have different number of cols.
        //  The explicit dlog works by itself in phpMyAdmin but the union fails at the
        //  point of union with core_trans.dlog
        if ($date1 != date('Y-m-d') && $date2 >= date('Y-m-d')) {
            $dlog_spec = " union all select 
tdate, register_no, emp_no, trans_no, upc, trans_type, trans_subtype, trans_status,
department, quantity, unitPrice, total, tax, foodstamp, itemQtty,
0 AS memType, 0 AS staff, 0 AS numflag, '  ' AS charflag,
card_no, trans_id, trans_num
from core_trans.dlog";
            if (substr($dlog,0,1) == '(') {
                $dlog = '(' .
                    trim($dlog,'()') .
                    $dlog_spec .
                    ')';
            } else {
                $dlog = '(select * from ' .
                    $dlog .
                    $dlog_spec .
                    ')';
            }
        }

        //obs? $cardOp = ($card_no == 0)? ">" : "=";
        $query = "SELECT d.card_no,
            d.tdate,
            DATE_FORMAT(d.tdate,'%Y %m %d %l:%i') AS 'SortDate',
            DATE_FORMAT(d.tdate,'%M %e, %Y %l:%i%p') AS 'When',
            CASE WHEN (d.card_no > 99900)
                    THEN a.LastName
                    ELSE CONCAT(a.FirstName,' ',a.LastName)
                END AS 'Who',
            trans_status,
            unitPrice,
            quantity,
            total
            FROM $dlog d
            LEFT JOIN {$FANNIE_OP_DB}.custdata a ON a.CardNo = d.card_no
            WHERE d.department = 1005
              AND (tdate BETWEEN ? AND ?)
			ORDER BY DATE_FORMAT(d.tdate, '%Y-%m-%d %H:%i')";
        $args = array();
        $args[] = $date1 . ' 00:00:00';
        $args[] = $date2 . ' 23:59:59';
	
		$prep = $dbc->prepare($query);
        if ($prep === False) {
            $dbc->logger("\nprep failed:\n$query");
        }
		$result = $dbc->execute($prep,$args);
        if ($result === False) {
            $dbc->logger("\nexec failed:\n$query\nargs:",implode(" : ",$args));
        }

		/**
		  Simple report
		  Issue a query, build array of results
		*/
		$ret = array();
        $programBankNumber = 99989;
        $transferOut = 0;
        $otherOut = 0;
        $rowCount = 0;
		while ($row = $dbc->fetchArray($result)){
			$memberNumber = $row['card_no'];
            $suffix = "";
            if ($row['trans_status'] == 'V') {
                $suffix = "Void";
                //continue;
            }
            if ($row['trans_status'] == 'R') {
                if ($memberNumber == $programBankNumber) {
                    $transferOut += $row['total'];
                } else {
                    // What are these?
                    $otherOut += $row['total'];
                }
                $suffix = "Refund";
                continue;
            }
			$record = array();
			//$record[] = $row[0]."/".$row[1]."/".$row[2];
			$record[] = $row['SortDate'];
			$record[] = $row['When'];
            $record[] = "<a href='../CoopCred1/index.php?memNum={$row['card_no']}&amp;program=CoopCred'
                target='_CCR_{$row['card_no']}' title='Details for this member'>{$row['card_no']}</a>";
            $record[] = "<a href='../CoopCred1/index.php?memNum={$row['card_no']}&amp;program=CoopCred'
                target='_CCR_{$row['card_no']}' title='Details for this member'>{$row['Who']}</a>";
			$record[] = (($memberNumber == $programBankNumber)?"Input":"Payment") . $suffix;
            // Needs %f.2 or number_format($foo,2), still addable for footer total.
            $record[] = sprintf("%.2f",($memberNumber == $programBankNumber)
                                    ? $row['total']
                                    : (-1 * $row['total']));
			$ret[] = $record;
            $rowCount++;
		}

		return $ret;

    // /fetch_report_data()
	}

	function report_description_content(){
		$ret = array();
		$date1 = FormLib::get_form_value('date1',date('Y-m-d'));
		$date2 = FormLib::get_form_value('date2',date('Y-m-d'));
        $ret[] = "Events in the WEFC Store Program Department (1005) from " .
            (($date1 == '')?"Program start":$date1) .
            " to " .
            (($date2 == '')?date('Y-m-d'):$date2);
            //(($date2 == '')?"Today":$date2);
            //(($date2 == '')?"Yesterday":$date2);
		//$ret[] = "For member #".FormLib::get_form_value('card_no');
        if (($date1 == $date2) && ($date2 = date('Y-m-d'))) {
		    $today_time = date("l F j, Y g:i A");
            $ret[] = "As at: {$today_time}";
        } else {
		    $today_time = date("l F j, Y g:i A");
            $ret[] = "As at: {$today_time}";
            //$today_time = date("l F j, Y");
            //$ret[] = "To the end of the day <b>before</b> this day: {$today_time}";
        }
		return $ret;
	}
	
	/**
	  Sum the total columns
	*/
	function calculate_footers($data){
		$sumProgram = 0.0;
		foreach($data as $row){
			$sumProgram += $row[5];
		}
		return array('Balance',null,null,null,null,number_format($sumProgram,2));
	}

	function form_content(){
?>
<div id=main>	
<form method = "get" action="StoreProgramReport.php">
	<table border="0" cellspacing="0" cellpadding="5">
		<tr>
            <td colspan=99><span style='font-weight:bold;'>Leave dates empty to report on the whole life of the program.</span>
<br/>The final Balance is relative to the Balance at Start Date.
            </td>
		</tr>
		<tr>
			<th>Date Start</th>
			<td>	
		               <input type=text size=14 id=date1 name=date1 onfocus="this.value='';showCalendarControl(this);">
			</td>
			<td rowspan="3">
			<?php echo FormLib::date_range_picker(); ?>
<!-- Leave dates empty to report on the whole life of the program. -->
			</td>
		</tr>
		<tr>
			<th>End</th>
			<td>
		                <input type=text size=14 id=date2 name=date2 onfocus="this.value='';showCalendarControl(this);">
		       </td>

		</tr>
		<tr>
			<td> <input type=reset name=reset value="Clear Form"> </td>
			<td> <input type=submit name=submit value="Submit"> </td>
		</tr>
		<tr> 
			<th><!-- Member# --></th>
			<td style='padding-bottom:0;'><!--
			<input type=text name=card_no size=14 id=card_no  />
-->
			</td>
			<td>
			<input type="checkbox" name="excel" id="excel" value="xls" />
			<label for="excel">Excel</label>
			</td>	
		</tr>
	</table>
			<input type=hidden name=card_no id=card_no value=0  />
</form>
</div>
<?php
	}
}

FannieDispatch::conditionalExec(false);
?>
