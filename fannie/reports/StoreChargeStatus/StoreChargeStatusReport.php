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
/* TODO
 * [ ] Bootstrap the form. Ugly but started at least.
 * [ ] The exclude cardNo is probably not useful, a relic of an ancestor.
 * [ ] Get memberType <select> from the table.
 */

include(dirname(__FILE__) . '/../../config.php');
include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');

class StoreChargeStatusReport extends FannieReportPage
{

    public $description = '[AR/Store Charge Status Report] Charges and Payments in Store Charge Accounts
        (where the member "runs a tab", has a debit balance).';
    public $report_set = 'Membership';
    public $themed = true;

    protected $defaultStartDate = "1970-01-01";
    protected $dateFrom = "";
    protected $dateTo = "";
    protected $errors = array();
    protected $reportType;
    protected $memType;
    protected $placeholders;
    protected $multi_report_mode = false;
    protected $no_sort_but_style = true;
    protected $netText = "";
    // For in-page sorting
    protected $sortable = false;
    protected $sort_column;
    protected $sort_direction;
    // For database
    protected $dbSortOrder;

	function preprocess(){
        /* 18Nov2015 Is the modern way but results not happy
         *  at least when done here.
         *  The crucial thing for bootstrap is to set $new_tablesorter
         * parent::preprocess();
         */
		global $dbc, $FANNIE_WINDOW_DRESSING, $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

		/**
		  Set the page header and title, set caching
		*/
		$this->title = "Store Charge - Status Report";
		$this->header = "Store Charge - Status Report";
		$this->report_cache = 'none';

        if (isset($_REQUEST['submit'])) {

            // Radios
		    $this->reportType = FormLib::get_form_value('reportType','summary');
            $this->netText = ($this->reportType == "detail") ? _("Net") : _("Period Balance");
		    $this->dbSortOrder = FormLib::get_form_value('dbSortOrder','DESC');
            // Checkboxes
		    $this->placeholders = FormLib::get_form_value('placeholders','0');
		    $this->sortable = FormLib::get_form_value('sortable','0');
		    $this->subTotals = FormLib::get_form_value('subTotals','0');
            if ($this->sortable == True) {
                //$this->sortable = True; // False to not use sorting column heads.
                if ($this->reportType == 'summary') {
                    $this->sort_column = 1; // 1st column is 0
                    $this->sort_direction = 0; // 0=asc 1=desc
                    $this->header .= " - Summary";
                } else {
                    // The initial sort of col-heads moves the subtotals out of place.
                    if ($this->subTotals) {
                        $this->sortable = False;
                    }
                    $this->sort_column = 3; // 1st column is 0
                    // 0=asc 1=desc
                    // Name is always ASC. DESC applies to date.
                    $this->sort_direction = 0;
                    //$this->sort_direction = (($this->dbSortOrder == 'DESC')?1:0);
                    $this->header .= " - Detail";
                }
            } else {
                $this->no_sort_but_style = true;
            }

		    $this->memType = FormLib::get_form_value('memType','');
            if ($this->memType) {
                $mt = new MemtypeModel($dbc);
                $mt->memtype($this->memType);
                $mt->load();
                $memTypeDesc = $mt->memDesc();
                $this->header .= " - $memTypeDesc";
            }

		    $dateFrom = FormLib::get_form_value('date1','');
		    $dateTo = FormLib::get_form_value('date2','');
            $this->dateFrom = (($dateFrom == '')?$this->defaultStartDate:$dateFrom);
            $this->dateTo = (($dateTo == '')?date('Y-m-d'):$dateTo);

			if (isset($FANNIE_WINDOW_DRESSING) && $FANNIE_WINDOW_DRESSING == True) {
				$this->has_menus(True);
                $this->new_tablesorter = true;
            } else {
				$this->has_menus(False);
            }

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
			// $this->add_script("../../src/CalendarControl.js");
            $noop = 1;
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
        $paymentsText = _("Payments");
        $ret[] = sprintf("<p class='explain'>Store Charge: %s, Purchases and %s
            <br />from %s to %s",
            $paymentsText,
            $this->netText,
            (($this->dateFrom == $this->defaultStartDate)?"Program Start":
                date("F j, Y",strtotime("$this->dateFrom"))),
            (($this->dateTo == date('Y-m-d'))?"Today":
                date("F j, Y",strtotime("$this->dateTo")))
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
        $ret[] = "<p class='explain'><b>Purchases</b> is the value".
            " of merchandise the member has charged to the account.".
        "</p>";
        $ret[] = "<p class='explain'><b>Payments</b> are amounts".
            " the member has tendered against an outstanding balance.".
        "</p>";
        if ($this->reportType == "summary") {
            $ret[] = "<p class='explain'><b>{$this->netText}</b> is".
                " Purchases minus Payments during the stated date range.".
            "</p>";
        }
        if ($this->reportType == "detail") {
            $ret[] = "<p class='explain'><b>{$this->netText}</b> on each line".
                " is Purchases minus Payment in that transaction.".
            "</p>";
        }
        $ret[] = "<p class='explain'>Positive amounts in <b>{$this->netText}</b>".
           " are amounts owed to the co-op.".
            "</p>";
        if ($this->reportType == "summary") {
            $ret[] = "<p class='explain'><b>Current Balance</b> is the".
                " since-the-beginning, up-to-the-minute balance in the account.".
                " Positive amounts are owed to the co-op.".
            "</p>";
        }
        $ret[] = "<p class='explain'><b>Total</b> of <b>Purchase</b>".
            " at the <a href='#notes' style='text-decoration:underline;'>end</a> of the report".
            " is the retail value of what has been".
            " taken from inventory.</p>";
        $ret[] = "<p class='explain'><b>Total</b> of <b>{$this->netText}</b>".
            " at the <a href='#notes' style='text-decoration:underline;'>end</a> of the report".
            " is the difference between" .
            " the the amount that Members have".
            " charged to their accounts (Purchases)".
            " and the amount they have" .
            " paid to their accounts (Payments)".
            " during the period of the report".
            ".".
            "</p>";
        if ($this->reportType == "summary") {
            $ret[] = "<p class='explain'><b>Total</b> of <b>Current Balance</b>".
                " at the <a href='#notes' style='text-decoration:underline;'>end</a> of the report".
                " is the since-the-beginning, up-to-the-minute difference between" .
                " the the amount that Members have".
                " charged to their accounts (Purchases)".
                " and the amount they have" .
                " paid to their accounts (Payments)".
                ".".
                " If positive it is the total debt owed by members to the co-op at this moment." .
            "</p>";
        }
        $ret[] = "<p class='explain'><a href='" . $_SERVER['PHP_SELF'] . "'>Start over</a>" .
                "" .
                "</p>";
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
        // ChargeOK - always the same
        //$args[] = 1;
        // MemDiscountLimit >.. ChargeOK=1 OR ...
        //$args[] = 0;
        // Include CardNo >=
        $args[] = 1;
        //$args[] = 99900;
        // Exclude CardNo
        $args[] = 0;
        $memTypeSegment = '';
        if ($this->memType) {
            $memTypeSegment = ' AND c.memType = ?';
            $args[] = $this->memType;
        }
        if ($this->reportType == "detail") {
            $args[] = "$this->dateFrom 00:00:00";
            $args[] = "$this->dateTo 23:59:59";
        }
        // For second part of the UNION.
        if ($this->dateTo == date('Y-m-d')) {
            // Include CardNo >=
            $args[] = 1;
            //$args[] = 99900;
            // Exclude CardNo
            $args[] = 0;
            if ($this->memType) {
                $args[] = $this->memType;
            }
        }

        if (!class_exists("CustdataModel")) {
            include($FANNIE_ROOT.'classlib2.0/data/models/op/CustdataModel.php');
        }
        $limitColName = "MemDiscountLimit";
        if (class_exists("CustdataModel")) {
            $custModel = new CustdataModel($dbc);
            $custCols = $custModel->getColumns();
            if (array_key_exists("ChargeLimit",$custCols)) {
                $limitColName = "ChargeLimit";
            } elseif (array_key_exists("MemDiscountLimit",$custCols)) {
                $limitColName = "MemDiscountLimit";
            } else {
                $this ->errors[] = "No charge limit field: using default $limitColName";
            }
        }
        $limitColumn = " c.{$limitColName} AS 'Limit',";

        if ($this->reportType == "detail") {
            $this->report_headers = array('Date','When','Member#','Member Name',
                'Receipt',
                '$ '._('Purchase'),
                '$ '._('Payment'),
                '$ '._('Net'));
            $queryPast = "(SELECT a.card_no AS card_no,
                        a.tdate AS OrderDate,
                        DATE_FORMAT(a.tdate,'%Y %m %d %H:%i') AS 'SortDate',
                        DATE_FORMAT(a.tdate,'%M %e, %Y %l:%i%p') AS 'When',
                        a.charges,
                        a.trans_num,
                        a.payments,
                        (a.charges - a.payments) AS 'Net',
                        year(a.tdate) AS tyear, month(a.tdate) AS tmonth, day(a.tdate) AS tday,
                        c.CardNo AS CardNo,{$limitColumn}
                        c.FirstName AS FirstName,
                        c.LastName AS LastName
                    FROM {$FANNIE_OP_DB}{$dbc->sep()}custdata AS c
                        JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_history AS a
                            ON a.card_no = c.CardNo
                    WHERE (c.ChargeOK = 1 OR c.{$limitColName} > 0)
                        AND c.cardNo >= ?
                        AND c.cardNo != ?{$memTypeSegment}
                        AND (a.tdate BETWEEN ? AND ?))";

            $queryToday = " (SELECT a.card_no AS card_no,
                    a.tdate AS OrderDate,
                    DATE_FORMAT(a.tdate,'%Y %m %d %H:%i') AS 'SortDate',
                    DATE_FORMAT(a.tdate,'%M %e, %Y %l:%i%p') AS 'When',
                    a.charges,
                    a.trans_num,
                    a.payments,
                    (a.charges - a.payments) AS 'Net',
                    year(a.tdate) AS tyear, month(a.tdate) AS tmonth, day(a.tdate) AS tday,
                    c.CardNo AS CardNo,{$limitColumn}
                    c.FirstName AS FirstName,
                    c.LastName AS LastName
                FROM {$FANNIE_OP_DB}{$dbc->sep()}custdata AS c
                    JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_history_today AS a
                        ON a.card_no = c.CardNo
                WHERE (c.ChargeOK = 1 OR c.{$limitColName} > 0)
                    AND c.cardNo >= ?
                    AND c.cardNo != ?{$memTypeSegment})";

            $queryOrder = "\nORDER BY LastName ASC, FirstName, CardNo, OrderDate {$this->dbSortOrder}";
            $queryUnion = "\nUNION\n";

            // #'qIf the order is DESC and the range includes today then
            //  the ar_history_today select needs to be first.
            if ($this->dateTo == date('Y-m-d')) {
                if ($this->dbSortOrder == 'DESC') {
                    $args = array();
                    // ChargeOK - always the same
                    //$args[] = 1;
                    // MemDiscountLimit >.. ChargeOK=1 OR ...
                    //$args[] = 0;
                    //if ($this->dateTo == date('Y-m-d')) {
                        // Include CardNo >=
                        $args[] = 1;
                        // Exclude CardNo
                        $args[] = 0;
                    //}
                    // For second part of the UNION.
                    // Include CardNo >=
                    $args[] = 1;
                    // Exclude CardNo
                    $args[] = 0;
                    if ($this->memType) {
                        $args[] = $this->memType;
                    }
                    $args[] = "$this->dateFrom 00:00:00";
                    $args[] = "$this->dateTo 23:59:59";
                    $query = "$queryToday $queryUnion $queryPast $queryOrder";
                } else {
                    $args = array();
                    // ChargeOK - always the same
                    //$args[] = 1;
                    // MemDiscountLimit >.. ChargeOK=1 OR ...
                    //$args[] = 0;
                    // Include CardNo >=
                    $args[] = 1;
                    // Exclude CardNo
                    $args[] = 0;
                    if ($this->memType) {
                        $args[] = $this->memType;
                    }
                    $args[] = "$this->dateFrom 00:00:00";
                    $args[] = "$this->dateTo 23:59:59";
                    // For second part of the UNION.
                    //if ($this->dateTo == date('Y-m-d')) {
                        // Include CardNo >=
                        $args[] = 1;
                        // Exclude CardNo
                        $args[] = 0;
                    //}
                    $query = "$queryPast $queryUnion $queryToday $queryOrder";
                }
            } else {

                $args = array();
                // ChargeOK - always the same
                //$args[] = 1;
                // MemDiscountLimit >.. ChargeOK=1 OR ...
                //$args[] = 0;
                // Include CardNo >=
                $args[] = 1;
                // Exclude CardNo
                $args[] = 0;
                if ($this->memType) {
                    $args[] = $this->memType;
                }
                $args[] = "$this->dateFrom 00:00:00";
                $args[] = "$this->dateTo 23:59:59";
                $query = "$queryPast $queryOrder";
            }

            /* If this isn't the first column in the output table
             * If using sortable tables the order on the page
             *  is controlled by the jquery tablesorter plugin
             *  which is configured in FannieReportPage
             *  to default to sort on the first column, acending.
             * $this->sortable = False; // False to not use sorting column heads.
             * $this->sort_column = 1; // 1st column is 0
             * $this->sort_direction = 0; // 1=desc
             * A corollary of this scheme is that it isn't possible
             *  to sort on a field that isn't in the HTML table,
             *  or on a treatment of the field that is not in the HTML table.
             */

        // #'s
        } elseif ($this->reportType == "summary") {
            $this->report_headers = array(
                'Member#','Member Name',
                '$ '._('Purchases'),
                '$ '._('Payments'),
                '$ '.$this->netText,
                '$ '._('Current Balance')
            );
            // How is placeholders used now?
            $ph1 = ($this->placeholders)?'LEFT ':'';
            $OP = "{$FANNIE_OP_DB}{$dbc->sep()}";
            $TRANS = "{$FANNIE_TRANS_DB}{$dbc->sep()}";
            /*
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE SUM(a.payments) END AS payments,
                    CASE WHEN a.charges IS NULL THEN 0.00 ELSE SUM(a.charges) END AS charges,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE SUM(a.charges - a.payments) END AS 'Net',
                    AND (a.tdate IS NULL OR (a.tdate BETWEEN ? AND ?))
             */
        $query = "(SELECT c.CardNo AS CardNo,
                   CASE WHEN a.card_no IS NULL THEN 'no' ELSE 'yes' END AS activity,
                    CASE WHEN a.tdate IS NULL THEN '$this->dateFrom 00:00:00' ELSE a.tdate END AS OrderDate,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE a.payments END AS payments,
                    CASE WHEN a.charges IS NULL THEN 0.00 ELSE a.charges END AS charges,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE (a.charges - a.payments) END AS 'Net',
                    {$limitColumn}
                    c.FirstName AS FirstName,
                    c.LastName AS LastName,
                    r.balance as live_balance
                FROM {$FANNIE_OP_DB}{$dbc->sep()}custdata AS c
                {$ph1}JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_live_balance AS r
                    ON r.card_no = c.CardNo
                {$ph1}JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_history AS a
                    ON a.card_no = c.CardNo
                WHERE (c.ChargeOK = 1 OR c.{$limitColName} > 0)
                    AND c.cardNo >= ?
                    AND c.cardNo != ?{$memTypeSegment}
                )";
                    //GROUP BY c.CardNo
        // If range includes today, need UNION with ar_history_today
        // Don't select placeholders (LEFT JOIN)
        if ($this->dateTo == date('Y-m-d')) {
            $query .= "\nUNION";
            $query .= "\n(SELECT c.CardNo AS CardNo,
                    CASE WHEN a.card_no IS NULL THEN 'no' ELSE 'yes' END AS activity,
                    CASE WHEN a.tdate IS NULL THEN '$this->dateFrom 00:00:00' ELSE a.tdate END AS OrderDate,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE a.payments END AS payments,
                    CASE WHEN a.charges IS NULL THEN 0.00 ELSE a.charges END AS charges,
                    CASE WHEN a.payments IS NULL THEN 0.00 ELSE (a.charges - a.payments) END AS 'Net',
                    {$limitColumn}
                    c.FirstName AS FirstName,
                    c.LastName AS LastName,
                    r.balance as live_balance
                FROM {$FANNIE_OP_DB}{$dbc->sep()}custdata AS c
                    JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_history_today AS a
                        ON a.card_no = c.CardNo
                    JOIN {$FANNIE_TRANS_DB}{$dbc->sep()}ar_live_balance AS r
                        ON r.card_no = c.CardNo
                WHERE (c.ChargeOK = 1 OR c.{$limitColName} > 0)
                    AND c.cardNo >= ?
                    AND c.cardNo != ?{$memTypeSegment}
                )";
                    //GROUP BY c.CardNo
        } else {
            $query = trim($query,"()");
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

        $dateArgs = "&date1={$this->dateFrom}&date2={$this->dateTo}";
        $ARreport = "ArWithDatesReport";

        if ($this->reportType == "detail") {
            // #'dCompose the rows of the table.
            $lastCardNo = 0;
            $lastLastName = "";
            // Array of cells of a row in the report table.
            $record = array();
            $subtotalCharges = 0;
            $subtotalPayments = 0;
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
                    $record[] = number_format($subtotalCharges,2);
                    $record[] = number_format($subtotalPayments,2);
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
                $record[] = "<a href='../AR/{$ARreport}.php?memNum={$row['CardNo']}{$dateArgs}'".
                    " target='_SCR_{$row['CardNo']}' title='Details for this member'>{$row['CardNo']}</a>";
                //Member Name
                $memberName = sprintf("%s, %s", $row['LastName'], $row['FirstName']);
                $record[] = "<a href='../AR/{$ARreport}.php?memNum={$row['CardNo']}{$dateArgs}'".
                    " target='_SCR_{$row['CardNo']}' title='Details for this member'>{$memberName}</a>";
                //trans_num
                $record[] = sprintf("<a href='%sadmin/LookupReceipt/RenderReceiptPage.php?".
                    "year=%d&month=%d&day=%d&receipt=%s' target='_Receipt_%s'>%s</a>",
                    "$FANNIE_URL",
                    $row['tyear'],$row['tmonth'],$row['tday'],
                    $row['trans_num'],$row['trans_num'],$row['trans_num']);
                $record[] = $row['charges'];
                    $subtotalCharges += $row['charges'];
                $record[] = $row['payments'];
                    $subtotalPayments += $row['payments'];
                $record[] = $row['Net'];
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
                    $record[] = number_format($subtotalCharges,2);
                    $record[] = number_format($subtotalPayments,2);
                    $record[] = number_format($subtotalNet,2);
                    $ret[] = $record;
            }
        // detail
        }

        /* Summary, consolidating today and before-today rows for the same member.
         * Compose the rows of the report table.
         * #'s
         */
        if ($this->reportType == "summary") {
            $lastCardNo = 0;
            $record = array('','',0.00,0.00,0.00,0.00);
            $rowCount = 0;
            $dateFromFull = "$this->dateFrom 00:00:00";
            $dateToFull = "$this->dateTo 23:59:59";
            while ($row = $dbc->fetch_array($results)) {
                if ($row['CardNo'] != $lastCardNo && $lastCardNo != 0) {
                    if ($this->placeholders || ($record[2] != 0.00 || $record[3] != 0.00)) {
                        $record[2] = sprintf("%.2f",$record[2]);
                        $record[3] = sprintf("%.2f",$record[3]);
                        $record[4] = sprintf("%.2f",$record[4]);
                        $record[5] = sprintf("%.2f",$record[5]);
                        $ret[] = $record;
                    }
                    // Array of cells of a row in the report table.
                    $record = array('','',0.00,0.00,0.00,0.00);
                }
                if ($row['CardNo'] != $lastCardNo) {
                    //Member Number
                    if ($this->report_format == 'html') {
                        $record[0] = "<a href='../AR/{$ARreport}.php?" .
                            "memNum={$row['CardNo']}{$dateArgs}'" .
                            " target='_AR_{$row['CardNo']}' " .
                            "title='Details for this member'>{$row['CardNo']}</a>";
                    } else {
                        $record[0] = $row['CardNo'];
                    }
                    //Member Name
                    $memberName = sprintf("%s, %s%s", $row['LastName'], $row['FirstName'],
                        ($row['activity']=='no')?' (n/a)':'');
                    if ($this->report_format == 'html') {
                        $record[1] = "<a href='../AR/{$ARreport}.php?" .
                            "memNum={$row['CardNo']}{$dateArgs}'".
                            " target='_AR_{$row['CardNo']}' " .
                            "title='Details for this member'>{$memberName}</a>";
                    } else {
                        $record[1] = $memberName;
                    }
                    if ($row['OrderDate'] >= $dateFromFull && $row['OrderDate'] <= $dateToFull) {
                        $record[2] = $row['charges'];
                        $record[3] = $row['payments'];
                        $record[4] = $row['Net'];
                    }
                    $record[5] = $row['live_balance'];
                } else {
                    if ($row['OrderDate'] >= $dateFromFull && $row['OrderDate'] <= $dateToFull) {
                        // For the CSV format cannot assign to $record this way
                        //  if the earlier elements were done by $record[]=.
                        $record[2] += $row['charges'];
                        $record[3] += $row['payments'];
                        $record[4] += $row['Net'];
                    }
                    //$record[5] = $row['live_balance'];
                }
                $lastCardNo = $row['CardNo'];
                $rowCount++;
            }
            if ($rowCount > 0) {
                $record[2] = sprintf("%.2f",$record[2]);
                $record[3] = sprintf("%.2f",$record[3]);
                $record[4] = sprintf("%.2f",$record[4]);
                $record[5] = sprintf("%.2f",$record[5]);
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
		$ret = array();
        $ret[] = "<p class='explain'><br /><a name='notes'><b>Notes:</b></a></p>";
        $ret[] = "<p class='explain'><b>Total</b> of <b>Purchases</b>".
            " is the retail value of what has been".
            " taken from inventory.</p>";
        $ret[] = "<p class='explain'><b>Total</b> of <b>{$this->netText}</b>".
            " is the difference between" .
            " the the amount that Members have".
            " charged to their accounts (Purchases)".
            " and the amount they have" .
            " paid to their accounts (Payments)".
            " during the period of the report".
            ".".
            "</p>";
        $ret[] = "<p class='explain'>Positive amounts in <b>{$this->netText}</b>".
           " are amounts owed to the co-op.".
            "</p>";
        if ($this->reportType == "summary") {
            $ret[] = "<p class='explain'><b>Total</b> of <b>Current Balance</b>".
                " is the since-the-beginning, up-to-the-minute difference between" .
                " the the amount that Members have".
                " charged to their accounts (Purchases)".
                " and the amount they have" .
                " paid to their accounts (Payments)".
                ".".
                " If positive it is the total debt owed by members to the co-op at this moment." .
            "</p>";
        }
		return $ret;
    // /report_end_content()
	}
	
	/**
	  Sum the total columns
	*/
	function calculate_footers($data){
		$sumCharges = 0.0;
		$sumPayments = 0.0;
		$sumNet = 0.0;
		$sumLive = 0.0;
        if ($this->reportType == "detail") {
            foreach($data as $row) {
                if (strpos($row[3],"Subtotal") !== False)
                    continue;
                //fix
                $sumCharges += (isset($row[5]))?$row[5]:0;
                $sumPayments += (isset($row[6]))?$row[6]:0;
                $sumNet += (isset($row[7]))?$row[7]:0;
            }
            $ret = array();
            $ret[] = array(null,null,null,null,null,'$ Purchases','$ Payments',"\$ {$this->netText}");
            $ret[] = array('Totals',null,null,null,null,
                number_format($sumCharges,2),
                number_format($sumPayments,2),
                number_format($sumNet,2)
            );
        } elseif ($this->reportType == "summary") {
            foreach($data as $row) {
                $sumCharges += (isset($row[2]))?$row[2]:0;
                $sumPayments += (isset($row[3]))?$row[3]:0;
                $sumNet += (isset($row[4]))?$row[4]:0;
                $sumLive += (isset($row[5]))?$row[5]:0;
            }
            $ret = array();
            $ret[] = array(null,null,'$ Purchases','$ Payments',"\$ {$this->netText}", '$ Current Balance');
            $ret[] = array('Totals',null,
                number_format($sumCharges,2),
                number_format($sumPayments,2),
                number_format($sumNet,2),
                number_format($sumLive,2)
            );
        }
        return $ret;
    // /calculate_footers()
	}


    /** The form for specifying the report
     */
    function form_content(){

?>
<div id=main>    
<?php
        if (isset($this->errors[0])) {
            echo "<p style='font-family:Arial; font-size:1.5em;'>";
            echo "<b>Errors in previous run:</b>";
            $sep = "<br />";
            foreach ($this->errors as $error) {
                echo "{$sep}$error";
            }
            echo "</p>";
        }
?>
</div><!-- /#main -->

<!-- Bootstrap-coded begins -->
<form method = "get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="form-horizontal">
<div class="row">
    <div class="col-sm-6"><!-- left column -->
<!-- Restore these two tags to put the dates to the right -->
    <!-- /div --><!-- /.col-sm-6 -->
    <!-- div class="col-sm-5" -->
        <div class="form-group">
            <label class="col-sm-3 control-label">Start Date</label>
            <div class="col-sm-9">
                <input type=text id=date1 name=date1 class="form-control date-field" />
                <p class="explain" style="float:none; margin:0 0 0 0.5em;">Leave
                    both dates empty to report on all items to date.
                <br />Leave Start date empty to report from the beginning.
                </p>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">End Date</label>
            <div class="col-sm-9">
                <input type=text id=date2 name=date2 class="form-control date-field" />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">Member Type</label>
            <div class="col-sm-5">
                <select name="memType" class="form-control">
                    <option value="">All Member Types</option>
                    <option value="4">Eater</option>
                    <option value="6">Worker</option>
                    <option value="7">Intra-Coop</option>
                </select>
            </div>
        </div>
    </div><!-- /.col-sm-# left column -->

    <div class="col-sm-6">
        <div class="form-group">
            <!-- label class="col-sm-1 control-label"> &nbsp; </label -->
            <div class="col-sm-9">
                <?php echo FormLib::date_range_picker(); ?>                            
            </div>
        </div>
    </div><!-- /.col-sm-# right column -->
</div><!-- /.row -->

<div class="row">
</div><!-- /.row -->

<div class="row">
    <div class="col-sm-6">
        <div class="form-group">
            <div class="col-sm-5">
                <fieldset><legend>Report Type</legend></fieldset>
            </div>
            <div class="col-sm-7">
                <p>Report Type details</p>
            </div>
        </div><!-- /.form-group -->

        <div class="form-group">
            <div class="col-sm-4">
                <input type="radio" name="reportType" id="reportTypeSummary" value="summary" checked="yes" />
                <label for="summary"> Summary </label>
            </div><!-- /.col-sm-5 -->
            <div class="col-sm-7">
                <!-- class=checkbox renders hanging indent -->
                <label class="checkbox" for="placeholders">
                    <input type="checkbox" name="placeholders" id="placeholders" checked="yes"/>
                    List members who may make Store Charges but have not done so.
                </label>
            </div><!-- /.col-sm-7 -->
        </div><!-- /.form-group -->

        <div class="form-group">
            <div class="col-sm-4">
                <input type="radio" name="reportType" id="reportTypeDetail" value="detail" />
                <label for="detail"> Detail </label>
            </div><!-- /.col-sm-5 -->
            <div class="col-sm-7">
                <label class="checkbox" for="subTotals">
                    <input type="checkbox" name="subTotals" id="subTotals" checked="yes" />
                    Show a subtotal for each member.
                </label>
                <fieldset><legend>Order of detail items</legend>
        <!-- I don't know what "control-group" and "controls" do. -->
        <div class="control-group">
                    <!-- label class="control-label">Fieldset Name</label -->
            <div class="controls">
                    <label class="radio" for="dbSortOrder">
                        <input type="radio" name="dbSortOrder" id="dbSortOrderDESC" value="DESC" checked="yes"
                        /> Newest first
                    </label>
                    <!-- span class="help-inline">Supporting inline help text</span -->
                    <label class="radio" for="dbSortOrder">
                        <input type="radio" name="dbSortOrder" id="dbSortOrderASC" value="ASC" 
                        /> Oldest first
                    </label>
            </div><!-- /.controls -->
        </div><!-- /.control-group -->
                </fieldset>
            </div><!-- /.col-sm-7 -->
        </div><!-- /.form-group -->

<!-- Bootstrap notes
 applying class="form control" to <input>:
 - text:
   - box is as wide as the container
   - gives rounded corners
 - checkbox:
   - centered in the container
   - ~3x wider and higher than without the class
 - radio button:
   - centered in the container
   - ~3x diameter than without the class
     - class=input-small|medium|large etc don't affect size.
 - with fieldset:
   - adds a text-input-like box that does not encompass the fieldset
   - probably not meant for use with fieldset
 <fieldset> seems to
 - write the legend flush-left in a large font
 - with faint line below it, length of the containing div
    or possibly of the width=
 - bottom margin of nearly line-height
 - nested fieldset ?indented ~1"
<fieldset style="width:30em;"><legend>Summary</legend>
</fieldset>
title="Tick to display with sorting from column heads; un-tick for a plain formt."
-->

        <div class="form-group">
            <!-- Because I can't get the technique for subTotals or Summary/Detail to work here. -->
            <div class="col-sm-1">
            <input class="" type="checkbox" name="sortable" id="sortable" checked="yes" />
            </div><!-- /.col-sm-1 -->
            <div class="col-sm-4">
            <label class="" for="sortable" >
            Sort on Column Heads
            </label>
            </div><!-- /.col-sm-4 -->
        </div><!-- /.form-group -->
    </div><!-- /.col-sm-5 left column -->
</div><!-- /.row -->
<p>
        <button type=submit name=submit value="Create Report" class="btn btn-default">Create Report</button>
        <!-- button type=reset name=reset class="btn btn-default">Start Over</button -->
</p>
</form><!-- /.form-horizontal -->

<!-- Bootstrap-coded ends -->
<?php
    // /form_content()
    }

    public function helpContent()
    {
        $ret = "";

        $ret .= "<p>Display Summary, or by option Details, of Payments and Purchases
            and a Balance
            for each Member who has Store Charge (AR) activity during the period,
                and Grand Totals for all Members.
                </p>";
        $ret .= "<p>There is an option to list Members who may make Store Charges but
            have not done so during the period.
            </p>";
        $ret .= "<p>The Summary report has links to drill down to detail for each
            listed Member.
            </p>";

        return $ret;
    }


// /class
}

FannieDispatch::conditionalExec(false);

