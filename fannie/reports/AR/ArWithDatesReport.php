<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
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

class ArWithDatesReport extends FannieReportPage 
{
    public $description = '[AR/Store Charge by Date Range] lists all
        AR/Store Charge transactions for a given member for a range of dates';
    public $report_set = 'Membership';
    public $themed = true;

    protected $report_headers = array('Date', 'Receipt', 'Amount', 'Type', 'Running');
    // Sort direction. 0 is ascending, 1 is descending
    protected $sort_direction = 0;
    protected $title = "Fannie : Store Charge Activity by Date Range Report";
    protected $header = "Store Charge Activity by Date Range Report";
    protected $required_fields = array('memNum');
    protected $defaultStartDate = "1970-01-01";
    protected $memNum = 0;
    protected $dateFrom = "";
    protected $dateTo = "";
    protected $excel = "";
    protected $memberFullName = "";
    protected $memChargeLimit = 0.00;
    protected $memAvailableBalance = 0.00;
    protected $hasDates = false;
    protected $errors = array();

    function preprocess()
    {
        /* 18Nov2015 Is the modern way but results may not be happy.
         *  The crucial thing for bootstrap is to set $new_tablesorter
         * parent::preprocess();
         */

        $this->memNum = FormLib::get_form_value('memNum',0);
        $dateFrom = FormLib::get_form_value('date1','');
        $dateTo = FormLib::get_form_value('date2','');
        $this->hasDates = (($dateFrom . $dateTo) == '') ? false : true;
        $this->dateFrom = (($dateFrom == '')?$this->defaultStartDate:$dateFrom);
        $this->dateTo = (($dateTo == '')?date('Y-m-d'):$dateTo);

        if ($this->memNum == 0) {
            $this->content_function = "form_content";
        } else {
            $dbc = $this->connection;
            $dbc->selectDB($this->config->get('OP_DB'));

            $TRANS  = "{$this->config->get('TRANS_DB')}{$dbc->sep()}";

            $q = "SELECT FirstName, LastName, ChargeLimit,
                CASE WHEN start_date IS NULL THEN '' ELSE date(start_date) END as startDate,
                CASE WHEN b.balance IS NULL THEN '' ELSE b.balance END as currentBalance,
                CASE WHEN b.availBal IS NULL THEN '' ELSE availBal END as availBal
                FROM custdata c
                LEFT JOIN memDates d on d.card_no = c.CardNo
                LEFT JOIN {$TRANS}memChargeBalance b on b.cardNo = c.CardNo
                WHERE c.CardNo = ?  AND c.personNum =1";
            $s = $dbc->prepare($q);
            $args = array($this->memNum);
            $r = $dbc->execute($s,$args);
            if ($dbc->num_rows($r) > 0) {
                $mb = $dbc->fetch_array($r);
                $this->memberFullName = sprintf("%s %s",
                    $mb['FirstName'], $mb['LastName']);
                /* Default to Start of Membership.
                 * Not sure this will always work as expected and may not matter anyway.
                if (! $dateFrom && $mb['startDate']) {
                    $this->dateFrom = $mb['startDate'];
                }
                 */
                $this->memChargeLimit = ($mb['ChargeLimit'] == '') ? 'unknown' :
                    $mb['ChargeLimit'];
                $this->memAvailableBalance = ($mb['availBal'] == '') ? 'unknown' :
                    $mb['availBal'];
            } else {
                $this->errors[] = "Error: Member {$this->memNum} is not known.";
                return False;
            }


			/**
			  Format-related settings.
			*/
            $this->excel = FormLib::get_form_value('excel','');
            if ($this->excel == 'xls') {
				$this->report_format = 'xls';
				$this->has_menus(False);
            } elseif ($this->excel == 'csv') {
				$this->report_format = 'csv';
				$this->has_menus(False);
            } elseif ($this->config->get('WINDOW_DRESSING')) {
                $this->has_menus(true);
                /* sort_direction needed for Opening Balance to be at the top.
                 * This overrides the database ORDER BY if they are different.
                 */
                $this->sort_direction = 0;
                /* For the zebra effect. Don't really want sortable.
                 */
                $this->new_tablesorter = true;
                /* This loses the zebra effect, makes it hard to read.
                 * Also generally small and ugly.
                $this->sortable = false;
                $this->no_sort_but_style = true;
                 */
            } else {
                $this->has_menus(false);
            }
            $this->content_function = "report_content";
        }
        return True;

        // preprocess()
    }

    /**
      Define any CSS needed
      @return A CSS string
    */
    function css_content(){
    $css =
"p.explain {
    font-family: Arial;
    font-size: 1.0em;
    color: black;
    margin: 0 0 0 0;
}
";
    $css .=
    "p.expfirst {
        margin-top:1.2em;
}
";
    $css .=
"H4.report {
    line-height:1.3em;
    margin-bottom:0.2em;
}
";
        return $css;
    }

    public function report_description_content()
    {
        $desc = array();
        if (isset($this->errors[0])) {
            $desc[] = "<div id=errors>";
            $desc[] = "<p style='font-family:Arial; font-size:1.5em;'>";
            $desc[] = "<b>Errors in the previous run:</b>";
            $sep = "<br />";
            foreach ($this->errors as $error) {
                $desc[] = "{$sep}$error";
            }
            $desc[] = "</p>";
            $desc[] = "</div><!-- /#errors -->";
        }
        $msg = sprintf("For: %s (#%d)",
            $this->memberFullName,
            $this->memNum);
        $desc[] = "<h3>$msg</h3>";
        // Date range
        $desc[] = sprintf("<H4 class='report'>From %s to %s</H4>",
            (($this->dateFrom == $this->defaultStartDate)?_("The Beginning"):
                date("F j, Y",strtotime("$this->dateFrom"))),
            (($this->dateTo == date('Y-m-d'))?"Today":
                date("F j, Y",strtotime("$this->dateTo")))
        );
        // Today, until now (not necessarily the whole day).
       if (!$this->hasDates || $this->dateTo == date('Y-m-d')) {
            $today_time = date("l F j, Y g:i A");
            $desc[] = "<p class='explain'>As at: {$today_time}</p>";
        // Last day
        } else {
            $today_time = date("l F j, Y");
            $desc[] = "<p class='explain'>To the end of the day: {$today_time}</p>";
        }
        // Limit and Available Balance
        $msg = sprintf("Current limit: \$%s Available balance: \$%s as at %s",
            (!$this->excel) ?  number_format($this->memChargeLimit,2) :
                sprintf("%0.2f",$this->memChargeLimit),
            (!$this->excel) ?  number_format($this->memAvailableBalance,2) :
                sprintf("%0.2f",$this->memAvailableBalance),
            date("l F j, Y g:i A")
        );
        $desc[] = "<p class='explain'>{$msg}</p>";
        // Start over
        $midSpacer = '&nbsp; | &nbsp;';
        $startSpacer = '| &nbsp;';
        $endSpacer = '&nbsp; |';
        $msg = "<p>";
        $msg .= $startSpacer;
        $msg .= "<a href='{$_SERVER['PHP_SELF']}'>Start over</a>";
        $msg .= $midSpacer;
        $msg .= "<a href='{$_SERVER['PHP_SELF']}?" .
                "memNum={$this->memNum}'>Same member, all activities</a>";
        $msg .= $endSpacer;
        $msg .= "</p>";
        if (!$this->excel) {
            $desc[] = $msg;
        }
        if ($this->excel) {
            for ($i=0 ; $i<count($desc) ; $i++) {
                $desc[$i] = strip_tags($desc[$i]);
            }
        }
        return $desc;
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
        $msg = '';
        $uri = filter_input(INPUT_SERVER, 'REQUEST_URI');
        $json = FormLib::queryStringtoJSON(filter_input(INPUT_SERVER, 'QUERY_STRING'));
        $msg .= $startSpacer;
        $msg .= sprintf('<a href="?json=%s">Back</a>',
            base64_encode($json)
        );
        $msg .= $midSpacer;
        $msg .= "<a href='{$_SERVER['PHP_SELF']}'>Start over</a>";
        $msg .= $midSpacer;
        $msg .= "<a href='{$_SERVER['PHP_SELF']}?" .
                "memNum={$this->memNum}'>Same member, all activities</a>";
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


    /* #'r
     */
    public function fetch_report_data()
    {
        global $FANNIE_TRANS_DB, $FANNIE_URL;

        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('TRANS_DB'));
        $dateSpec = ($this->hasDates) ? " AND (s.tdate BETWEEN ? AND ?)" : '';

        $fromCols = "card_no,charges,payments,tdate,trans_num";
        $fromSpec = "(SELECT $fromCols FROM ar_history ";
        if (!$this->hasDates || $this->dateTo >= date('Y-m-d')) {
            $fromSpec .= "UNION SELECT $fromCols FROM ar_history_today";
        }
        $fromSpec .= ")";

        $q = $dbc->prepare("SELECT charges,trans_num,payments,
            year(tdate) AS year, month(tdate) AS month, day(tdate) AS day,
            date(tdate) AS ddate
                FROM $fromSpec AS s 
                WHERE s.card_no=?{$dateSpec}
                ORDER BY tdate ASC");
        $args=array($this->memNum);
        if ($this->hasDates) {
            // To support Opening Balance always start from the beginning.
            $args[] = "{$this->defaultStartDate} 00:00:00";
            //$args[] = "$this->dateFrom 00:00:00";
            $args[] = "{$this->dateTo} 23:59:59";
        }
        $r = $dbc->execute($q,$args);

        $data = array();
        $rrp  = "{$this->config->get('URL')}admin/LookupReceipt/RenderReceiptPage.php";
        $balance = 0.0;
        $opening = array();
        while($w = $dbc->fetch_row($r)) {
            if ($w['charges'] == 0 && $w['payments'] == 0) {
                continue;
            }
            $record = array();
            $record[] = $w['ddate'];
            if (FormLib::get('excel') !== '') {
                $record[] = $w['trans_num'];
            } else {
                // Receipt#, linked to Receipt Renderer, new tab
                $record[] = sprintf("<a href='{$rrp}?year=%d&month=%d&day=%d&receipt=%s' " .
                    "target='_SCRA_%s'" .
                    ">%s</a>",
                    $w['year'],$w['month'],$w['day'],
                    $w['trans_num'],
                    $w['trans_num'],
                    $w['trans_num']
                );
            }
            // Amount
            $record[] = sprintf('%.2f', (($w['charges'] != 0)
                ? (1 * $w['charges'])
                : (-1 * $w['payments']))
            );
            $record[] = $this->getTypeLabel($w['charges'],$w['payments']);
            $balance += ($w['charges'] != 0 ? (1 * $w['charges']) : (-1 * $w['payments']));
            $record[] = sprintf('%.2f',$balance);
            /* Opening Balance:
             * If the start of the requested date range is later than defaultStartDate
             * and the current item is dated earlier than the start of date range
             * set the running balance aside as opening balance
             * and when you get to the first date => the start of the range
             * display the opening balance and then the first wanted item.
             */
            if (($this->dateFrom > $this->defaultStartDate) &&
                ($w['ddate'] < $this->dateFrom)
            ) {
                $opening = $record;
                continue;
            }
            if (!empty($opening) && ($w['ddate'] >= $this->dateFrom)) {
                /* The year, month and day values have be be real, not 0.
                 * otherwise the JS sort sorts them at the end.
                 */
                $opening[0] = $this->dateFrom;
                $opening[1] = "--";
                $opening[2] = $opening[4];
                $opening[3] = _("Opening Balance");
                $data[] = $opening;
                $opening = array();
            }
            $data[] = $record;
        }

        return $data;
    }

    /* Return the appropriate label for the amount.
     */
    private function getTypeLabel ($charges, $payment) {
        $label = "None";
        if ($charges > 0) {
            $label = _("Charge");
        } elseif ($payment > 0) {
            $label = _("Payment");
        } elseif ($payment < 0) {
            $label = _("Transfer Out");
        } elseif ($charges < 0) {
            $label = _("Refund");
        } else {
            $label = _("No Movement");
        }

        return $label;
    }

    public function calculate_footers($data)
    {
        // NB: Refund is a reverse Charge, not a Payment.
        $incomeLabels = array(_('Payment'),
            'Opening','Balance', _('Opening Balance')
        );
        $outgoLabels = array(_('Charge'),_('Refund'),
            'Transfer Out'
        );
        $footers = array();
        $ret = array();
        $balance = 0.0;
        $income = 0.0;
        $outgo = 0.0;
        $uaf = 0.0;
        foreach($data as $record) {
            $balance += $record[2];
            if (
                in_array($record[3], $incomeLabels)
            ) {
                $income += $record[2];
            } elseif (
                in_array($record[3], $outgoLabels)
            ) {
                $outgo += $record[2];
            } else {
                $uaf += $record[2];
            }
        }

        // Subtotal of Payments/Earnings/Inputs
        $ret[0] = "Subtotal:";
        $ret[1] = '--';
        $ret[2] = (!$this->excel) ? number_format($income,2) :
            sprintf("%0.2f",$income);
        $ret[3] = _("Payments");
        $ret[4] = '--';
        $footers[] = $ret;

        // Subtotal of Charges/Purchases/Disbursements
        $ret[0] = "Subtotal:";
        $ret[1] = '--';
        $ret[2] = (!$this->excel) ? number_format($outgo,2) :
            sprintf("%0.2f",$outgo);
        $ret[3] = _("Charges");
        $ret[4] = '--';
        $footers[] = $ret;

        // Subtotal of the un-accounted-for.
        $total = 0.0;
        $total = ($income + $outgo);
        $net = ($total - $balance);
        if ($net < -0.01 || $net > 0.01) {
            $ret[0] = "Subtotal:";
            $ret[1] = '--';
            $ret[2] = (!$this->excel) ? number_format($net,2) :
                sprintf("%0.2f",$net2);
            $ret[3] = '--';
            $ret[3] = "Un-accounted for";
            $ret[4] = '--';
            $footers[] = $ret;
        }

        if ($balance < 0.00) {
            $balance = ($balance < 0)?0:$balance;
            $balanceColour = "black";
            $balanceLabel = "Available for<br />purchases:";
        } elseif ($balance == 0.00) {
            $balanceColour = "black";
            $balanceLabel = "Owes the<br />Coop:";
        } else {
            $balanceColour = "red";
            $balanceLabel = "Owes the<br />Coop:";
        }
        $ret[0] = (!$this->excel) ? "<span style='color:{$balanceColour};'>" .
            $balanceLabel . "</span>" :
            $balanceLabel;
        $ret[1] = '--';
        $ret[2] = (!$this->excel) ? "<span style='color:{$balanceColour};'>" .
            number_format($balance, 2) . "</span>" :
            sprintf("%0.2f",$balance);
        $ret[3] = '--';

        $footers[] = $ret;
        return $footers;

        // calculate_footers
    }


    public function form_content()
    {
        global $FANNIE_OP_DB;

        $this->add_onload_command('$(\'#memNum\').focus()');
        $dbc = $this->connection;
        $OP = "{$FANNIE_OP_DB}{$dbc->sep()}";
        $TRANS  = "{$this->config->get('TRANS_DB')}{$dbc->sep()}";
        $query = "SELECT c.CardNo, c.FirstName, c.LastName, c.Balance AS cdBalance,
            m.memDesc,
            CASE WHEN b.balance IS NULL THEN '' ELSE b.balance END as currentBalance
            FROM {$OP}custdata c
            INNER JOIN {$OP}memtype m on m.memtype = c.memType
            LEFT JOIN {$TRANS}memChargeBalance b on b.cardNo = c.CardNo
            WHERE c.personNum =1 AND (c.ChargeOk != 0 || c.Balance != 0)
            ORDER BY c.memType, LastName";
        $statement = $dbc->prepare($query);
        $rslt = $dbc->execute($statement,array());
        $memNumControl = '';
        $memberLabel = '';
        if ($dbc->numRows($rslt) < 200) {
            $memberLabel = 'Member';
            $memNumControl .= '<select name="memNum" class="form-control">
            <option value="">Select a Member</option>
            ';
            $opts = '';
            $lastMemDesc = '';
            while ($mb = $dbc->fetch_array($rslt)) {
                if ($mb['memDesc'] != $lastMemDesc) {
                    $opts .= sprintf("<option value=''>%s</option>",
                        "-- {$mb['memDesc']} --");
                    $lastMemDesc = $mb['memDesc'];
                }
                $opts .= sprintf("<option value='%d' %s>%d %s \$%s</option>",
                    $mb['CardNo'],
                    (($mb['CardNo'] == $this->memNum) ? 'SELECTED' : ''),
                    $mb['CardNo'],
                    ($mb['LastName'] . ', ' .  $mb['FirstName']),
                    (!$this->excel) ?  number_format($mb['currentBalance'],2) :
                        sprintf("%0.2f",$mb['currentBalance'])
                );
            }
            $memNumControl .= "{$opts}</select>";
        }
        else {
            $memberLabel = 'Member #';
            $memNumControl = '<input type="text" name="memNum" value="" class="form-control"
                required id="memNum" />';
        }

?>

    <form method="get" action=" <?php echo $_SERVER['PHP_SELF']; ?> " class="form-horizontal">
        <div id=errors>    
<?php
        if (isset($this->errors[0])) {
            echo "<p style='font-family:Arial; font-size:1.5em;'>";
            echo "<b>Errors in the previous run:</b>";
            $sep = "<br />";
            foreach ($this->errors as $error) {
                echo "{$sep}$error";
            }
            echo "</p>";
        }
?>
        </div><!-- /#errors -->
        
        <div class="row">
            <div class="col-sm-8">
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="memNum">
                    <?php echo $memberLabel; ?>
                    </label>
                    <div class="col-sm-6">
                        <?php echo $memNumControl; ?>
                    </div><!-- /.col-sm-6 -->
                </div><!-- /.form-group -->
        
        <div class="form-group">
            <label class="col-sm-3 control-label">Start Date</label>
            <div class="col-sm-4">
                <input type=text id=date1 name=date1 class="form-control date-field" />
            </div>
        </div><!-- /.form-group -->

        <div class="form-group">
            <div class="col-sm-12">
                <p>
<span style="font-weight:bold;">Leave both dates empty to report on all the activity of the member.</span>
<br/>Leave Start date empty to report from the beginning.</p>
            </div>
            </div><!-- /.form-group -->

        <div class="form-group">
            <label class="col-sm-3 control-label">End Date</label>
            <div class="col-sm-4">
                <input type=text id=date2 name=date2 class="form-control date-field" />
            </div>
        </div><!-- /.form-group -->

        <div class="form-group">
        <div class="col-sm-9">
<?php
        echo FormLib::date_range_picker();
?>
        </div><!-- /.col-sm-9 -->
        </div><!-- /.form-group -->

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="excel">Output to</label>
                    <div class="col-sm-3">
                        <select id="excel" name="excel" class="form-control">
                            <option value="" selected="">Screen</option>
                            <option value="xls">Excel</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div><!-- /.col-sm-3 -->
                </div><!-- /.form-group -->
        
        <div class="form-group">
        <!-- col-sm needed for indent? -->
        <div class="col-sm-8">
        <button type="submit" class="btn btn-default">Get Report</button>
        <button type="reset" class="btn btn-default">Reset Form</button>
        </div><!-- /.col-sm-4 -->
        </div><!-- /.form-group -->

        </div><!-- /.col-sm-8 -->
        </div><!-- /.row -->

        </form>

<?php
    }

    public function helpContent()
    {
        return '<p>
            Report Store Charge (AR, Accounts Receivable) activity for one member
            for a given date range, which can include the current day, defaulting to all
            activity.
            <br />The form has a picklist members who may use Store Charge or
            have an outstanding balance.
            <br />The report shows an "opening balance", a running balance reflecting each
            activity,
            totals of "charges" and "payments" and a final balance for the period.
            </p>';
    }

}

FannieDispatch::conditionalExec();

