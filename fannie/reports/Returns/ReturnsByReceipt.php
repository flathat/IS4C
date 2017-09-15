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
/* 17Jan2016 EL WIP submitted for comment.
 */
include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class ReturnsByReceipt extends FannieReportPage {

    protected $defaultStartDate = "1970-01-01";
    protected $required_fields = array('date1', 'date2');
    protected $report_cache = 'none';
    protected $storeID;
    protected $employeeID;
    protected $laneID;
    protected $dateFrom = "";
    protected $dateTo = "";
    protected $excel = "";
    protected $errors = array();
    protected $origSortColumn = "";
    //
    protected $reportType;
    //
    protected $cSeparator;
    // Footers
    protected $returnsCount;
    protected $returnsAmount;

    public $description = "[Returns By Receipt Report] shows returned/refunded items
        and the reason the item was returned.
        In Receipt order, with owner/member number.
        <br />Assumes nth comment applies to the nth return.";

    public $report_set = 'Other Reports';
    public $themed = true;

    function preprocess(){

        $this->title = "Fannie : Returns By Receipt Report";
        $this->header = "Returns By Receipt Report";
        $this->report_cache = 'none';
        $this->storeID = FormLib::get_form_value('storeID',-1);
        $this->sort_column = FormLib::get_form_value('sort_column',0);
        $this->origSortColumn = $this->sort_column; // for ORDER BY
        if (FormLib::get_form_value('sortable','') !== '') {
            $this->new_tablesorter = true;
            if ($this->storeID != -1 && $this-sort_column > 1) {
                $this->sort_column++;
            }
            $this->sortable = True;
        } else {
            $this->no_sort_but_style = true;
            $this->sortable = False;
        }
        $this->employeeID = FormLib::get_form_value('employeeID');
        $this->laneID = FormLib::get_form_value('laneID');
        $this->reportType = FormLib::get_form_value('reportType');

        $dateFrom = FormLib::get_form_value('date1','');
        $dateTo = FormLib::get_form_value('date2','');
        $this->dateFrom = (($dateFrom == '')?$this->defaultStartDate:$dateFrom);
        $this->dateTo = (($dateTo == '')?date('Y-m-d'):$dateTo);

        if (FormLib::get_form_value('date1') !== ''){
            $this->content_function = "report_content";

            /**
              Check if a non-html format has been requested
               from the links in the initial display, not the form. What?
            */
            if (FormLib::get_form_value('excel','') !== '') {
                $this->excel = FormLib::get_form_value('excel');
                $this->report_format = $this->excel;
                $this->has_menus(False);
                $this->cSeparator= "; ";
            } else {
                $this->cSeparator= "<br />";
            }
        }

        return True;

    // preprocess()
    }

    /**
      Define any CSS needed
      @return A CSS string
    */
    function css_content(){
        $css = ".explain {
            font-family: Arial;
            color: black;
    }
    ";
        $css .= "p.explain {
            font-family: Arial;
            font-size: 1.0em;
            color: black;
            margin: 0 0 0 0;
    }
    ";
        return $css;
    }

    function report_description_content(){
        $ret = array();

        $ret[] = sprintf("<p class='explain'><br />Returns and Refunds, with Reasons
            <br />from %s to %s",
            date("F j, Y",strtotime("$this->dateFrom")),
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
        $ret[] ="";
        if ($this->excel) {
            for ($i=0 ; $i<count($ret) ; $i++) {
                $ret[$i] = strip_tags($ret[$i]);
            }
        }

        return $ret;
    }

    /**
      Extra, non-tabular information appended to
      reports
      @return array of strings
    */
    public function report_end_content()
    {
        $desc = array();
        if ($this->excel != "") {
            return $desc;
        }
        $midSpacer = '&nbsp; | &nbsp;';
        $startSpacer = '| &nbsp;';
        $endSpacer = '&nbsp; |';
        //$hspacer = ' &nbsp; &nbsp; ';
        $msg = '';
        $uri = filter_input(INPUT_SERVER, 'REQUEST_URI');
        $json = FormLib::queryStringtoJSON(filter_input(INPUT_SERVER, 'QUERY_STRING'));
        $msg .= $startSpacer;
        $msg .= sprintf('<a href="?json=%s">Back</a>',
            base64_encode($json)
        );
        $msg .= $midSpacer;
        $msg .= "<a href='{$_SERVER['PHP_SELF']}'>Start over</a>";
        // Excel
        if (\COREPOS\Fannie\API\data\DataConvert::excelSupport()) {
            $msg .= $midSpacer;
            $msg .= sprintf('<a href="%s%sexcel=xls">Download Excel</a>',
                $uri,
                (strstr($uri, '?') === false ? '?' : '&')
            );
        }
        $msg .= $midSpacer;
        // CSV
        $msg .= sprintf('<a href="%s%sexcel=csv">Download CSV</a>',
            $uri,
            (strstr($uri, '?') === false ? '?' : '&')
        );
        $msg .= $endSpacer;
        $desc[] = "<p>{$msg}</p>";
        return $desc;
    }

    function fetch_report_data(){
        global $FANNIE_TRANS_DB, $FANNIE_OP_DB, $FANNIE_ARCHIVE_DB,
            $FANNIE_ARCHIVE_METHOD,
            $FANNIE_ROOT, $FANNIE_URL, $FANNIE_COOP_ID;

        /* Return value of the function,
         * an array of rows of table cell contents
         */
        $ret = array();

        $dbc = FannieDB::get($FANNIE_TRANS_DB);

        /* Return the SQL FROM parameter for a given date range.
         * This may not work for a range that starts before today
         *  and includes today; hence the manual union.
         */
        $dtrans = DTransactionsModel::selectDlog($this->dateFrom,$this->dateTo);
        $datestamp = $dbc->identifierEscape('datetime');

        if ( isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto' ) {
            //Is t. used
            //$shrinkageUsers = " AND (t.card_no not between 99900 and 99998)";
            $shrinkageUsers = "";
        } else {
            $shrinkageUsers = "";
        }

        // The eventual return value.
        // Or is it $ret?
                if ($this->storeID != -1) {
                    $store_id = 'store_id, ';
                } else {
                    $store_id = '';
                }
        $data = array();
        if ($this->reportType == "simple") {
            $this->report_headers = array('Date');
                $this->report_headers[] = _("Owner") . '#';
            // Maybe storeID s/b prefixed to receipt.
                if ($this->storeID != -1) {
                    $this->report_headers[] = 'Store';
                }
                $this->report_headers[] = 'Receipt';
                $this->report_headers[] = 'Item';
                $this->report_headers[] = 'Amount';
                $this->report_headers[] = 'Comments';
            $queryLimits = ""; //AND if limited by store, emp, reg
            $queryPast = "(SELECT tdate, date(tdate) AS ymd, {$store_id}
                register_no, emp_no, trans_no,
                upc, description, quantity, total, card_no
                FROM $dtrans
                WHERE trans_type in ('I','D')
                AND trans_status = 'R'
                AND tdate BETWEEN ? AND ?{$queryLimits}{$shrinkageUsers}
            )";
            /* Against dlog. $'t
             * -> Do this as a separate search rather than UNION.
             */
            $queryToday = "(SELECT tdate, date(tdate) AS ymd, {$store_id}
                register_no, emp_no, trans_no,
                upc, description, quantity, total, card_no
                FROM dlog
                WHERE trans_type in ('I','D')
                AND trans_status = 'R'{$queryLimits}{$shrinkageUsers}
            )";

            $queryOrder = "\nORDER BY tdate"; // ORDER BY store_id, tdate
            //OrderDate {$this->dbSortOrder}";
            $queryUnion = "\nUNION\n";

            if ($this->dateTo >= date('Y-m-d')) {
                $retArgs = array();
                $retArgs[] = "$this->dateFrom 00:00:00";
                $retArgs[] = "$this->dateTo 23:59:59";
                $query = "$queryToday $queryUnion $queryPast $queryOrder";
                //$query = "$queryPast $queryUnion $queryToday $queryOrder";
            } else {
                $retArgs = array();
                $retArgs[] = "$this->dateFrom 00:00:00";
                $retArgs[] = "$this->dateTo 23:59:59";
                $query = "$queryPast $queryOrder";
            }
        }
        /* reportType == "extended"
         * I don't think this is actually supported,
         *  and have forgotten what it was about.
         */
        else {
            // ->[o]
            $this->report_headers = array('Date');
                //$this->report_headers[] = _("Owner") . '#';
                if ($this->storeID != -1) {
                    $this->report_headers[] = 'Store';
                }
                $this->report_headers[] = 'Emp';
                $this->report_headers[] = 'Lane';
                $this->report_headers[] = 'Receipt';
                $this->report_headers[] = 'Code';
                $this->report_headers[] = 'Description';
                $this->report_headers[] = 'Qty';
                $this->report_headers[] = 'Amount';
                $this->report_headers[] = 'Comments';
        }

        $retsP = $dbc->prepare($query);
        $retsR = $dbc->execute($retsP, $retArgs);

        $today = date('Y-m-d');
        $TRANS = $FANNIE_TRANS_DB . $dbc->sep();
        $ARCHIVE = $FANNIE_ARCHIVE_DB . $dbc->sep();
        $returnsCount = 0;
        $returnsAmount = 0;
        $ert = "";
        $lastERT = "";
        $nthReturn = 0;
        while($row = $dbc->fetchRow($retsR)){
            if ($row['description'] == 'Bottle Return') {
                continue;
            }
            $returnsCount++;
            /* The part of the row for the Returned/Refunded item.
             * Comments will be appended later.
                    sprintf('$%s',number_format($s['costs'],2))
             */
            $memberLink = sprintf("<a href='{$FANNIE_URL}mem/" .
                "MemberEditor.php?memNum=%d" .
                "' " .
                "target='_blankm'>%d</a>",
                $row['card_no'],
                $row['card_no']);
            /* I didn't expect the JS sort would work without 0-padding,
             *  but it does.
            $ertSortable = sprintf("%04d-%02d-%03d",
             */
            $ertSortable = sprintf("%d-%d-%d",
                $row['emp_no'],
                $row['register_no'],
                $row['trans_no']);
            $ert = $row['emp_no'] . '-' . $row['register_no'] . '-' .
                $row['trans_no'];
            /* JS sort may not work on the receipt param which isn't 0-padded
             * In that case will need a separate column for sorting.
             */
            $receiptLink = sprintf("<a href='{$FANNIE_URL}admin/LookupReceipt/" .
                "RenderReceiptPage.php?receipt=%s&date=%s" .
                "' " .
                "target='_blank'>%s</a>",
                $ert,
                $row['ymd'],
                $ertSortable);
            if ($ert != $lastERT) {
                $nthReturn = 1;
            } else {
                $nthReturn++;
            }
            $lastERT = $ert;
            if ($this->reportType == "simple") {
                $record = array($row['ymd']);
                $record[] = $memberLink;
                //$record[] = $row['card_no'];
                if ($this->storeID != -1) {
                    $record[] = $row['store_id'];
                }
                //$record[] = $ertSortable;
                $record[] = $receiptLink;
                $record[] = $row['description'];
                $record[] = sprintf('%.2f',$row['total']);
            } else {
                $record = array($row['ymd']);
                $record[] = $row['card_no'];
                if ($this->storeID != -1) {
                    $record[] = $row['store_id'];
                }
                $record[] = $receiptLink;
                $record[] = $row['register_no'];
                $record[] = $row['emp_no'];
                $record[] = $row['upc'];
                $record[] = $row['description'];
                $record[] = sprintf('%.2f',$row['quantity']);
                $record[] = sprintf('%.2f',$row['total']);
            }
            $returnsAmount += $row['total'];
            /* Find all the comments in the whole transaction
             *  in the appropriate dtransactions table
             * YYYYMM for table
             * YYYY-MM-DD + 00:00:00 and 23:59:59 for datetime
             */
            if ($row['ymd'] == $today) {
                $tTable = "{$TRANS}dtransactions";
            } else {
                if (isset($FANNIE_ARCHIVE_METHOD) && 
                    $FANNIE_ARCHIVE_METHOD == 'partitions') {
                    $tTable = $ARCHIVE. "bigArchive";
                } else {
                    // $row['ymd'] contains 'YYYY-MM-DD' e.g. '2014-11-09'
                    $YYYYMM = substr($row['ymd'],0,4) .  substr($row['ymd'],5,2);
                    $tTable = $ARCHIVE. "transArchive" . $YYYYMM;
                }
            }
            $args = array();
            $args[] = $row['ymd'] . " 00:00:00";
            $args[] = $row['ymd'] . " 23:59:59";
            $args[] = $row['emp_no'];
            $args[] = $row['register_no'];
            $args[] = $row['trans_no'];
            /*
                AND trans_status not in ('X')
             */
            $transQ = "SELECT datetime, description
                FROM $tTable
                WHERE datetime between ? AND ?
                AND emp_no = ? AND register_no = ? AND trans_no = ?
                AND trans_subtype = 'CM'
                AND trans_status != 'X'
                ";
            $transS = $dbc->prepare($transQ);
            $transR = $dbc->execute($transS,$args);

            $rItems = 0;
            $rComments = array();
            while($rowR = $dbc->fetchRow($transR)){
                $rItems++;
                if ($rItems != $nthReturn) {
                    continue;
                }
                if ($rowR['description'] != "") {
                    $rComments[] =
                        preg_replace("/^RF: /","",$rowR['description']);
                }
            }
            if (count($rComments) == 0) {
                $rComments[] = "No comments";
            }
            $record[] = implode($this->cSeparator, $rComments);
            $data[] = $record;
        }

        $this->returnsCount = $returnsCount;
        $this->returnsAmount = $returnsAmount;

        return $data;

    // fetch_report_data()
    }

    // #'f
    public function calculate_footers($data)
    {
        $footers = array();
        if ($this->reportType == "simple") {
            $footers[] = _("Returns:") . " " .
                number_format($this->returnsCount,0);
            $footers[] = ""; // Owner
            $footers[] = "";
            $footers[] = "";
            $footers[] = 
                number_format($this->returnsAmount,2);
            $footers[] = "";
        } else {
            $footers[] = _("Returns:") . "x" .
                number_format($this->returnsCount,0);
        }
        return $footers;
    // calculate_footers()
    }

    function form_content(){
/*
 * 2 columns
 * Left:
 *  Date Start
 *  Date End
 *  Sortable
 *  Screen/xls/csv
 *  Submit(s)
 * Right:
 *  Date Picker
        <form method = "get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
*/
        ?>
<form method = "get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="form-horizontal">
<!-- Left column -->
<div class="col-sm-4">
    <div class="form-group"> 
        <label>Date Start</label>
        <input type=text id=date1 name=date1 required
            class="form-control date-field" />
    </div>
    <div class="form-group"> 
        <label>Date End</label>
        <input type=text id=date2 name=date2 required
            class="form-control date-field" />
    </div>

    <div class="form-group"> 
        <label for="sortable"
                title="If ticked the report can be re-sorted by clicking column
                heads." >Sort on col. heads</label>
        <input type=checkbox name=sortable id=sortable value=1 checked >
    </div>

<!-- $FANNIE_COOP_ID = 'RiverValleyMarket' -->
    <div class="form-group"> 
        <label for="sort_column">Sort on</label>
                <select id="sort_column" name="sort_column">
                    <option value="0">Date</option>
                    <option value="1">Owner</option>
                    <option value="2" selected="">Receipt</option>
                  </select>
    </div>

    <div class="form-group"> 
        <label for="excel">Format</label>
                <select id="excel" name="excel">
                    <option value="" selected="">Screen</option>
                    <option value="xls">Excel</option>
                    <option value="csv">CSV</option>
                  </select>
    </div>

    <div class="form-group"> 
        <button type=submit name=submit value="Submit"
            class="btn btn-default">Create Report</button>
        <!-- button type=reset name=reset value="Start Over"
            class="btn btn-default">Start Over</button -->
    </div>
</div>

<!-- Right column -->
<div class="col-sm-6">
    <?php echo FormLib::date_range_picker(); ?>
</div>
        <input type="hidden" id="storeID" name="storeID" value="-1" />
        <input type="hidden" id="employeeID" name="employeeID" value="-1" />
        <input type="hidden" id="laneID" name="laneID" value="-1" />
        <input type="hidden" id="reportType" name="reportType" value="simple" />
        </form>

        <?php
    // form_content()
    }

// ReturnsReport
}

FannieDispatch::conditionalExec(false);

?>
