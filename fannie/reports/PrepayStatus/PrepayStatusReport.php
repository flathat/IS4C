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

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class PrepayStatusReport extends FannieReportPage
{
    public $description = '[Prepay Status Report] Charges and Payments in Prepay Accounts
        (where the member pays ahead, has a credit balance).';
    public $report_set = 'Other Reports';
    public $themed = false;

    protected $defaultStartDate = "1970-01-01";
    protected $dateFrom = "";
    protected $dateTo = "";
    protected $errors = array();
    protected $reportType;
    protected $placeholders;
    // For in-page sorting
    protected $sortHeads;
    protected $sortable;
    protected $sort_column;
    protected $sort_direction;
    // For database
    protected $dbSortOrder;

	function preprocess(){
		global $dbc, $FANNIE_WINDOW_DRESSING, $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
		// Better from $FANNIE vars, if they existed.
        setlocale(LC_MONETARY, 'en_US');

		/**
		  Set the page header and title, set caching
		*/
		$this->title = "Prepay - Status Report";
		$this->header = "Prepay - Status Report";
		$this->report_cache = 'none';

        if (isset($_REQUEST['submit'])) {
			/**
			  Form submission occurred

			  Change content function, turn on/off the menus,
			  set up column heads
			*/

            // Radios
		    $this->reportType = FormLib::get_form_value('reportType','summary');
		    $this->dbSortOrder = FormLib::get_form_value('dbSortOrder','DESC');
            // Checkboxes
		    $this->placeholders = FormLib::get_form_value('placeholders','0');
		    $this->sortHeads = FormLib::get_form_value('sortHeads','0');
		    $this->subTotals = FormLib::get_form_value('subTotals','0');
            if ($this->sortHeads == True) {
                $this->sortable = True; // False to not use sorting column heads.
                if ($this->reportType == 'summary') {
                    $this->sort_column = 1; // 1st column is 0
                    $this->sort_direction = 0; // 0=asc 1=desc
                } else {
                    $this->sort_column = 3; // 1st column is 0
                    // 0=asc 1=desc
                    // Name is always ASC. DESC applies to date.
                    $this->sort_direction = 0;
                    //$this->sort_direction = (($this->dbSortOrder == 'DESC')?1:0);
                }
            } else {
                $this->sortable = False;
            }


		    $dateFrom = FormLib::get_form_value('date1','');
		    $dateTo = FormLib::get_form_value('date2','');
            $this->dateFrom = (($dateFrom == '')?$this->defaultStartDate:$dateFrom);
            $this->dateTo = (($dateTo == '')?date('Y-m-d'):$dateTo);

			if ( isset($FANNIE_WINDOW_DRESSING) && $FANNIE_WINDOW_DRESSING == True )
				$this->has_menus(True);
			else
				$this->has_menus(True);

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

            /* Lastly: Which page content to create upon return to draw_page().
             * That function may not be defined in this file.
             */
			$this->content_function = "report_content";
		} else {
            // create the default page content
			$this->add_script("../../src/CalendarControl.js");
        }

		return True;
	}

	/**
	  Define any CSS needed
	  @return A CSS string
	*/
	function css_content(){
        $css = "p.explain {
            font-family: Arial;
            font-size: 1.0em;
            color: black;
            margin: 0 0 0 0;
    }
    ";
        return $css;
    }

    /* Lines of descriptive text that appear before the tabular part of the
     * report.
     */
	function report_description_content(){
		global $FANNIE_URL;

        /* Each line of description is an element of this array.
         * At output <br /> will be prefixed unless the element starts with
         *  an HTML tag
         */
		$ret = array();
        $ret[] = "<p class='explain' style='font-size:13px;'><a href='{$FANNIE_URL}mem/MemCorrectionIndex.php'".
		" target='_Correct'>Corrections Utility Menu</a></p>";
        $paymentsText = _("Payments");
        $ret[] = sprintf("<p class='explain'><br />Prepay: {$paymentsText}, Purchases and Net
            <br />from %s to %s",
            (($this->dateFrom == $this->defaultStartDate)?"Program Start":date("F j, Y",strtotime("$this->dateFrom"))),
            (($this->dateTo == date('Y-m-d'))?"Today":date("F j, Y",strtotime("$this->dateTo")))
        );
        // Today, until now (not necessarily the whole day).
       if ($this->dateTo = date('Y-m-d')) {
		    $today_time = date("l F j, Y g:i A");
            $ret[] = "As at: {$today_time}</p>";
        // Last day
        } else {
            $today_time = date("l F j, Y");
            $ret[] = "To the end of the day: {$today_time}</p>";
        }
        $ret[] = "<p class='explain'><b>Total</b> of <b>Purchase</b>".
            " at the <a href='#notes' style='text-decoration:underline;'>end</a> of the report".
            " is the retail value of what has been".
            " taken from inventory.</p>";
        $ret[] = "<p class='explain'><b>Total</b> of <b>Net</b>".
            " at the <a href='#notes' style='text-decoration:underline;'>end</a> of the report".
            " is the difference between the the amount that has been put in Members'".
            " accounts (Payment) and the amount they have used for purchases.".
            " It is the amount the Coop is still liable for.<!--br /><br /--></p>";
        $ret[] ="";
		return $ret;
    // /report_description_content()
	}

    /* Get data from the database
     * and format it as a table without totals in the last row.
     */
	function fetch_report_data(){
		global $dbc, $FANNIE_TRANS_DB, $FANNIE_OP_DB, $FANNIE_ROOT, $FANNIE_URL;

        // Return value of the function,
        //  an array of rows of table cell contents
        $ret = array();

        $args = array();

        $args[] = "$this->dateFrom 00:00:00";
        $args[] = "$this->dateTo 23:59:59";
        // For second part of the UNION.
        if ($this->dateTo == date('Y-m-d')) {
            $noop=1;
        }

        if (!class_exists("CustdataModel")) {
            include($FANNIE_ROOT.'classlib2.0/data/models/CustdataModel.php');
        }
        $limitColName = "";
        if (class_exists("CustdataModel")) {
            $custModel = new CustdataModel($dbc);
            if (method_exists($custModel,"ChargeLimit")) {
                $limitColName = "ChargeLimit";
            } elseif (method_exists($custModel,"MemDiscountLimit")) {
                $limitColName = "MemDiscountLimit";
            }
        }
        $limitColumn = " c.{$limitColName} AS 'Limit',";

        if ($this->reportType == "detail") {
            $this->report_headers = array('Date','When','Member#','Member Name',
               'Receipt','$ '._('Payment'),'$ '._('Purchase'), '$ '._('Net'));
            $queryPast = "(SELECT a.card_no AS card_no,
                        a.tdate AS OrderDate,
                        DATE_FORMAT(a.tdate,'%Y %m %d %H:%i') AS 'SortDate',
                        DATE_FORMAT(a.tdate,'%M %e, %Y %l:%i%p') AS 'When',
                        a.charges,
                        a.trans_num,
                        a.payments,
                        (a.payments - a.charges) AS 'Net',
                        year(a.tdate) AS tyear, month(a.tdate) AS tmonth, day(a.tdate) AS tday,
                        c.CardNo AS CardNo,{$limitColumn}
                        c.FirstName AS FirstName,
                        c.LastName AS LastName
                    FROM {$FANNIE_OP_DB}{$dbc->sep()}custdata AS c
                        JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_history AS a
                            ON a.card_no = c.CardNo
                    WHERE (c.ChargeOK = 1 OR c.{$limitColName} > 0)
			    AND c.personNum = 1
                        AND (a.tdate BETWEEN ? AND ?))";

            $queryToday = " (SELECT a.card_no AS card_no,
                    a.tdate AS OrderDate,
                    DATE_FORMAT(a.tdate,'%Y %m %d %H:%i') AS 'SortDate',
                    DATE_FORMAT(a.tdate,'%M %e, %Y %l:%i%p') AS 'When',
                    a.charges,
                    a.trans_num,
                    a.payments,
                    (a.payments - a.charges) AS 'Net',
                    year(a.tdate) AS tyear, month(a.tdate) AS tmonth, day(a.tdate) AS tday,
                    c.CardNo AS CardNo,{$limitColumn}
                    c.FirstName AS FirstName,
                    c.LastName AS LastName
                FROM {$FANNIE_OP_DB}{$dbc->sep()}custdata AS c
                    JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_history_today AS a
                        ON a.card_no = c.CardNo
                WHERE (c.ChargeOK = 1 OR c.{$limitColName} > 0)
		    AND c.personNum = 1
                )";

            $queryOrder = "\nORDER BY LastName ASC, FirstName, CardNo, OrderDate {$this->dbSortOrder}";
            $queryUnion = "\nUNION\n";

            // If the order is DESC and the range includes today then
            //  the ar_history_today select needs to be first.
            if ($this->dateTo == date('Y-m-d')) {
                if ($this->dbSortOrder == 'DESC') {
                    $args = array();
                    $args[] = "$this->dateFrom 00:00:00";
                    $args[] = "$this->dateTo 23:59:59";
                    $query = "$queryToday $queryUnion $queryPast $queryOrder";
                } else {
                    $args = array();
                    $args[] = "$this->dateFrom 00:00:00";
                    $args[] = "$this->dateTo 23:59:59";
                    $query = "$queryPast $queryUnion $queryToday $queryOrder";
                }
            } else {

                $args = array();
                $args[] = "$this->dateFrom 00:00:00";
                $args[] = "$this->dateTo 23:59:59";
                $query = "$queryPast $queryOrder";
            }

        } elseif ($this->reportType == "summary") {
            $this->report_headers = array(
                'Member#','Member Name',
                '$ '._('Payments'),'$ '._('Purchases'), '$ '._('Net')
            );
            $ph1 = ($this->placeholders)?'LEFT ':'';
        $query = "(SELECT a.card_no AS card_no,
                    CASE WHEN a.card_no IS NULL THEN 'no' ELSE 'yes' END AS activity,
                    CASE WHEN a.tdate IS NULL THEN '$this->dateFrom 00:00:00' ELSE a.tdate END AS OrderDate,
                    CASE WHEN a.charges IS NULL THEN 0.00 ELSE SUM(a.charges) END AS charges,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE SUM(a.payments) END AS payments,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE SUM(a.payments - a.charges) END AS 'Net',
                    c.CardNo AS CardNo,{$limitColumn}
                    c.FirstName AS FirstName,
                    c.LastName AS LastName
                FROM {$FANNIE_OP_DB}{$dbc->sep()}custdata AS c
                {$ph1}JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_history AS a
                        ON a.card_no = c.CardNo
                WHERE (c.ChargeOK = 1 OR c.{$limitColName} > 0)
		    AND c.personNum = 1
                    AND (a.tdate IS NULL OR (a.tdate BETWEEN ? AND ?))
                GROUP BY c.CardNo)";
        // If range includes today, need UNION with ar_history_today
        // Don't select placeholders (LEFT JOIN)
        if ($this->dateTo == date('Y-m-d')) {
            $query .= "\nUNION";
            $query .= "\n(SELECT a.card_no AS card_no,
                    CASE WHEN a.card_no IS NULL THEN 'no' ELSE 'yes' END AS activity,
                    CASE WHEN a.tdate IS NULL THEN '$this->dateFrom 00:00:00' ELSE a.tdate END AS OrderDate,
                    CASE WHEN a.charges IS NULL THEN 0.00 ELSE SUM(a.charges) END AS charges,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE SUM(a.payments) END AS payments,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE SUM(a.payments - a.charges) END AS 'Net',
                    c.CardNo AS CardNo,{$limitColumn}
                    c.FirstName AS FirstName,
                    c.LastName AS LastName
                FROM {$FANNIE_OP_DB}{$dbc->sep()}custdata AS c
                    JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_history_today AS a
                        ON a.card_no = c.CardNo
                WHERE (c.ChargeOK = 1 OR c.{$limitColName} > 0)
		    AND c.personNum = 1
                GROUP BY c.CardNo)";
        }
        $query .= "\nORDER BY LastName, FirstName, CardNo, OrderDate";
        // summary
        }

        $statement = $dbc->prepare_statement("$query");
        if ($statement === False) {
            $ret[] = "***Error preparing: $query";
            return $ret;
        }
        $results = $dbc->exec_statement($statement,$args);
        if ($results === False) {
            $allArgs = implode(' : ',$args);
            $ret[] = "***Error executing: $query with: $allArgs";
            return $ret;
        }

        if ($this->reportType == "detail") {
            // Compose the rows of the table.
            $lastCardNo = 0;
            $lastLastName = "";
            // Array of cells of a row in the report table.
            $record = array();
            $subtotalPayments = 0;
            $subtotalCharges = 0;
            $subtotalNet = 0;
            $rowCount = 0;
            while ($row = $dbc->fetch_array($results)) {
                // If member has changed and a per-member subtotal is wanted
                //  put that out.
                if ($this->subTotals == True && $row['CardNo'] != $lastCardNo && $lastCardNo != 0) {
                    // Compose the subtotal row.
                    $record = array();
                    $record[] = ' &nbsp; ';
                    $record[] = ' &nbsp; ';
                    $record[] = ' &nbsp; ';
                    $record[] = "<b>Subtotal</b> ({$lastLastName})";
                    $record[] = ' &nbsp; ';
                    $record[] = number_format($subtotalPayments,2);
                    $record[] = number_format($subtotalCharges,2);
                    $record[] = number_format($subtotalNet,2);
                    // Add the subtotal row to the table
                    $ret[] = $record;
                    $subtotalPayments = 0.00;
                    $subtotalCharges = 0.00;
                    $subtotalNet = 0.00;
                    // Array of cells of a row in the report table.
                }
                $record = array();
                $record[] = $row['SortDate'];
                $record[] = $row['When'];
                //Member Number
                    $record[] = "<a href='../../mem/MemberEditor.php?memNum={$row['CardNo']}'".
                        " target='_Edit_{$row['CardNo']}' title='Edit this member'>{$row['CardNo']}</a>";
                //Member Name
                $memberName = sprintf("%s, %s", $row['LastName'], $row['FirstName']);
                $record[] = "<a href='../AR/index.php?memNum={$row['CardNo']}'".
                    " target='_AR_{$row['CardNo']}' title='Details for this member'>{$memberName}</a>";
                //trans_num
                $record[] = sprintf("<a href='%sadmin/LookupReceipt/RenderReceiptPage.php?".
                    "year=%d&month=%d&day=%d&receipt=%s' target='_Receipt'>%s</a>",
                    "$FANNIE_URL",
                    $row['tyear'],$row['tmonth'],$row['tday'],$row['trans_num'],$row['trans_num']);
                    $record[] = sprintf("%.2f",$row['payments']);
                    $subtotalPayments += $row['payments'];
                    $record[] = sprintf("%.2f",$row['charges']);
                    $subtotalCharges += $row['charges'];
                    $record[] = sprintf("%.2f",$row['Net']);
                    $subtotalNet += $row['Net'];
                $ret[] = $record;
                $lastCardNo = $row['CardNo'];
                $lastLastName = $row['LastName'];
                $rowCount++;
            }
            // Last Subtotal
            if ($this->subTotals == True && $rowCount > 0) {
                    $record = array();
                    $record[] = ' &nbsp; ';
                    $record[] = ' &nbsp; ';
                    $record[] = ' &nbsp; ';
                    $record[] = 'Subtotal';
                    $record[] = ' &nbsp; ';
                    $record[] = number_format($subtotalPayments,2);
                    $record[] = number_format($subtotalCharges,2);
                    $record[] = number_format($subtotalNet,2);
                    $ret[] = $record;
            }
        // detail
        }

        // Summary, consolidating today and before-today rows for the same member.
        // Compose the rows of the table.
        if ($this->reportType == "summary") {
            $lastCardNo = 0;
            $record = array();
            $rowCount = 0;
            while ($row = $dbc->fetch_array($results)) {
                if ($row['CardNo'] != $lastCardNo && $lastCardNo != 0) {
                    $record[2] = number_format($record[2],2);
                    $record[3] = number_format($record[3],2);
                    $record[4] = number_format($record[4],2);
                    $ret[] = $record;
                    // Array of cells of a row in the report table.
                    $record = array();
                }
                if ($row['CardNo'] != $lastCardNo) {
                    //Member Number
                    $record[] = "<a href='../../mem/MemberEditor.php?memNum={$row['CardNo']}'".
                        " target='_Edit_{$row['CardNo']}' title='Edit this member'>{$row['CardNo']}</a>";
                    //Member Name
                    $memberName = sprintf("%s, %s%s", $row['LastName'], $row['FirstName'],
                        ($row['activity']=='no')?' (n/a)':'');
                    $record[] = "<a href='../AR/index.php?memNum={$row['CardNo']}'".
                        " target='_AR_{$row['CardNo']}' title='Activity for this member'>{$memberName}</a>";
                    $record[2] = $row['payments'];
                    $record[3] = $row['charges'];
                    $record[4] = $row['Net'];
                } else {
                    $record[2] += $row['payments'];
                    $record[3] += $row['charges'];
                    $record[4] += $row['Net'];
                }
                $lastCardNo = $row['CardNo'];
                $rowCount++;
            }
            if ($rowCount > 0) {
                    $record[2] = number_format($record[2],2);
                    $record[3] = number_format($record[3],2);
                    $record[4] = number_format($record[4],2);
                $ret[] = $record;
            }
            // summary
            }

		return $ret;

    // /fetch_report_data()
	}

	/**
	  Extra, non-tabular information appended to reports
	  @return array of strings
	*/
	function report_end_content(){
		global $FANNIE_URL;
		$ret = array();
        $ret[] = "<p class='explain'><br /><a name='notes'><b>Notes:</b></a></p>";
        $ret[] = "<p class='explain'><b>Total</b> of <b>Purchases</b>".
            " is the retail value of what has been".
            " taken from inventory.</p>";
        $ret[] = "<p class='explain'><b>Total</b> of <b>Net</b>".
            " is the difference between the the amount that has been put in Members'".
            " accounts (Payment) and the amount they have used for purchases.".
            " It is the amount the Coop is still liable for.</p>";
        $ret[] = "<p class='explain' style='font-size:13px;'><a href='{$FANNIE_URL}mem/MemCorrectionIndex.php'".
		" target='_Correct'>Corrections Utility Menu</a></p>";
		return $ret;
    // /report_end_content()
	}
	
	/**
	  Sum the total columns
	*/
	function calculate_footers($data){
		$sumPayments = 0.0;
		$sumCharges = 0.0;
		$sumNet = 0.0;
        if ($this->reportType == "detail") {
            foreach($data as $row) {
                if (strpos($row[3],"Subtotal") !== False)
                    continue;
                $sumPayments += (isset($row[5]))?$row[5]:0;
                $sumCharges += (isset($row[6]))?$row[6]:0;
                $sumNet += (isset($row[7]))?$row[7]:0;
            }
            $ret = array();
            $ret[] = array(null,null,null,null,null,'$ Payments','$ Purchases','$ Net');
            $ret[] = array('Totals',null,null,null,null,
                number_format($sumPayments,2),
                number_format($sumCharges,2),
                number_format($sumNet,2)
            );
        } elseif ($this->reportType == "summary") {
            foreach($data as $row) {
                $sumPayments += (isset($row[2]))?$row[2]:0;
                $sumCharges += (isset($row[3]))?$row[3]:0;
                $sumNet += (isset($row[4]))?$row[4]:0;
            }
            $ret = array();
            $ret[] = array(null,null,'$ Payments','$ Purchases','$ Net');
            $ret[] = array('Totals',null,
                number_format($sumPayments,2),
                number_format($sumCharges,2),
                number_format($sumNet,2)
            );
        }
        return $ret;
    // /calculate_footers()
	}

    /** The form for specifying the report
     */
	function form_content(){
        global $dbc;
?>
<div id=main>	
<?php
        if (!empty($this->errors)) {
            echo "<p style='font-family:Arial; font-size:1.5em;'>";
            echo "<b>Errors in previous run:</b>";
            $sep = "<br />";
            foreach ($this->errors as $error) {
                echo "{$sep}$error";
            }
            echo "</p>";
        }
?>
<form method = "get" action="PrepayStatusReport.php">

	<table border="0" cellspacing="0" cellpadding="5" style="margin:1em 0 0 0;" width="95%">

		<tr style="vertical-align:top;">
			<th>Start Date</th>
			<td colspan="2">	
                <div style="float:left; margin: .25em 0.5em 0 0;">
               <input type=text size=14 id=date1 name=date1
                onfocus="this.value='';showCalendarControl(this);">
                </div>
                <p class="explain" style="float:none; margin:0 0 0 .5em;">
Leave both dates empty to report on all items to date.
                <br />Leave Start date empty to report from the beginning.
                </p>
			</td>
		</tr>

		<tr style="vertical-align:top;">
			<th>End Date</th>
			<td>
                <input type=text size=14 id=date2 name=date2
                onfocus="this.value='';showCalendarControl(this);">
		       </td>
		</tr>

		<tr>
            <th colspan=1>Report Type</td>
            <td colspan=1>
<fieldset style="width:30em;"><legend>Summary</legend>
<div style="float:left; margin-right:1.0em;">
<input type="radio" name="reportType" id="reportType" value="summary" checked="yes" />
<label for="summary"> Summary </label>
</div>

<fieldset style="width:20em;">
<input type="checkbox" name="placeholders" id="placeholders" />
<label for="placeholders">List members who can Prepay but have not done so.</label>
</fieldset>
</fieldset>

<fieldset style="width:30em;"><legend>Detail</legend>
<div style="float:left; margin-right:1.0em;">
<input type="radio" name="reportType" id="reportType" value="detail" />
<label for="detail"> Detail </label>
</div>
    <fieldset style="width:20em;">
    <input type="checkbox" name="subTotals" id="subTotals checked="yes" />
    <label for="subTotals">Show a subtotal for each member.</label>
        <fieldset style="width:15em;"><legend>Order of items</legend>
        <input type="radio" name="dbSortOrder" id="dbSortOrder" value="DESC" checked="yes" />
        <label for="dbSortOrder"> Newest first </label>
        <input type="radio" name="dbSortOrder" id="dbSortOrder" value="ASC" />
        <label for="dbSortOrder"> Oldest first </label>
        </fieldset>
    </fieldset>
</fieldset>
            </td>
		</tr>

		<tr>
            <th colspan=1>Sortable</td>
            <td colspan=1>
<input type="checkbox" name="sortHeads" id="sortHeads" checked="yes" />
<label for="sortHeads">Enable re-sorting the report by clicking column heads.</label>
            </td>
		</tr>

		<tr>
			<td> <input type='reset' name='reset' value="Clear Form"> </td>
			<td> <input type='submit' name='submit' value="Submit"> </td>
		</tr>

	</table>
</form>
</div>
<?php
    // /form_content()
	}

// /class
}

FannieDispatch::conditionalExec(false);
?>
