<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
    Copyright 2014 West End Food Co-op, Toronto, Canada

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

include(dirname(__FILE__) . '/../../../../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class ActivityReport extends FannieReportPage 
{
    public $description = '[Coop Cred Activity Report] lists all Coop Cred
        transactions for a given member in a given program';
    public $report_set = 'CoopCred';
    protected $title = "Fannie : Coop Cred Activity Report for a Single Member";
    protected $header = "Coop Cred Single Member Activity Report";

    protected $errors = array();
    // headers vary by Program
    protected $report_headers = array('Date', 'Receipt', 'Amount', 'Type', 'Running');
    protected $sort_direction = 0;
    protected $required_fields = array('memNum', 'programID');
    protected $cardNo = 0;
    protected $programID = 0;
    protected $programBankID = 0;
    protected $programName = '';
    protected $memberFullName = '';
    protected $programStartDate = "";
    protected $dateFrom = "";
    protected $dateTo = "";
    protected $epoch = "1970-01-01";
    protected $hasDates = false;

	public function preprocess()
    {
        global $FANNIE_OP_DB,$FANNIE_PLUGIN_LIST,$FANNIE_PLUGIN_SETTINGS;

        if (!isset($FANNIE_PLUGIN_LIST) || !in_array('CoopCred', $FANNIE_PLUGIN_LIST)) {
            // Would like to log the problem. Or display the form-page with the error
            //  like the Tools do.
            $this->errors[] = "The Coop Cred plugin is not enabled.";
            return False;
        }

        if (array_key_exists('CoopCredDatabase', $FANNIE_PLUGIN_SETTINGS) &&
            $FANNIE_PLUGIN_SETTINGS['CoopCredDatabase'] != "") {
                $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['CoopCredDatabase']);
        } else {
            // Would like to log the problem.
            $this->errors[] = "The Coop Cred database is not assigned in the " .
                "Coop Cred plugin configuration.";
            return False;
        }

        $this->cardNo = FormLib::get('memNum','');
        $this->programID = FormLib::get('programID','');
        if (strpos($this->cardNo, '|')) {
            list($this->programID, $this->cardNo) = explode('|',$this->cardNo);
        }

        if ($this->cardNo && $this->programID) {
            $ccpModel = new CCredProgramsModel($dbc);
            $ccpModel->programID($this->programID);
            $prog = array_pop($ccpModel->find());
            if ($prog != null) {
                $this->programName = $prog->programName();
                $this->programBankID = $prog->bankID();
            } else {
                $this->errors[] = "Error: Program ID {$this->programID} is not known.";
                return False;
            }

            $dbc = FannieDB::get($FANNIE_OP_DB);
            $cdModel = new CustdataModel($dbc);
            $cdModel->CardNo($this->cardNo);
            $cdModel->personNum(1);
            $mem = array_pop($cdModel->find());
            if ($mem != null) {
                $this->memberFullName = $mem->FirstName() . " " . $mem->LastName();
            } else {
                $noop = 1;
                $this->errors[] = "Error: Member {$this->cardNo} is not known.";
                return False;
            }

            $this->programStartDate = (preg_match("/^[12]\d{3}-\d{2}-\d{2}/",$prog->startDate()))
                ? $prog->startDate() : '1970-01-01';

		    $dateFrom = FormLib::get_form_value('date1','');
		    $dateTo = FormLib::get_form_value('date2','');
            $this->hasDates = (($dateFrom . $dateTo) == '') ? false : true;
            $this->dateFrom = (($dateFrom == '')?$this->programStartDate:$dateFrom);
            $this->dateTo = (($dateTo == '')?date('Y-m-d'):$dateTo);

            $this->sortable = (FormLib::get_form_value('sortable','') != '') ? True : False;
        }

        return parent::preprocess();
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
        global $FANNIE_URL;
        $desc = array();
        $msg = sprintf("For: %s (#%d) in: %s",
            $this->memberFullName,
            $this->cardNo,
            $this->programName);
        $desc[] = "<h3>$msg</h3>";
        // Date range
        $desc[] = sprintf("<H4 class='report'>From %s to %s</H4>",
            (($this->dateFrom == $this->programStartDate)?"Program Start":
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
        // Start over
        $msg = "<p><a href='{$_SERVER['PHP_SELF']}'>Start over</a>";
        $msg .= " &nbsp; &nbsp; ";
        $msg .= "<a href='{$_SERVER['PHP_SELF']}?" .
            "programID={$this->programID}&amp;memNum={$this->cardNo}'>Same member, all activities</a>";
        $msg .= "</p>";
        $desc[] = $msg;
        return $desc;
    }

	public function fetch_report_data()
    {
        global $FANNIE_TRANS_DB, $FANNIE_URL;
        global $FANNIE_PLUGIN_SETTINGS;

        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['CoopCredDatabase']);
        $dateSpec = ($this->hasDates) ? " AND (s.tdate BETWEEN ? AND ?)" : '';

        $fromCols = "programID,cardNo,charges,payments,tdate,transNum";
        $fromSpec = "(SELECT $fromCols FROM CCredHistory ";
        if (!$this->hasDates || $this->dateTo == date('Y-m-d')) {
            $fromSpec .= "UNION SELECT $fromCols FROM CCredHistoryToday";
        }
        $fromSpec .= ")";

// <<<<<<< HEAD Clean up cruft.
        /*
        $q = "SELECT cardNo,charges,transNum,payments,
            year(tdate) AS year, month(tdate) AS month, day(tdate) AS day,
            date(tdate) AS ddate
                FROM $fromSpec AS s 
                WHERE s.cardNo=?
                AND programID=?{$dateSpec}
                ORDER BY tdate ASC,transNum";
         */
        $q = "SELECT cardNo,charges,transNum,payments,
            year(tdate) AS year, month(tdate) AS month, day(tdate) AS day,
            date(tdate) AS ddate
            , tdate
                FROM $fromSpec AS s 
                WHERE programID=?{$dateSpec}
                ORDER BY tdate ASC,transNum";
        /* Need to parse transNum and sort the parts numerically
         * or reformat it as %05d-%05d-%05d so it will sort alpha.
         * Same in Liability report.
         */
        $s = $dbc->prepare($q);
        $args=array($this->programID);
        //$args=array($this->cardNo,$this->programID);
        if ($this->hasDates) {
            $args[] = "{$this->epoch} 00:00:00";
            $args[] = "{$this->dateTo} 23:59:59";
        }
        $r = $dbc->execute($s,$args);
/*======= Delete if report is OK cruft
        START >>>>>>> upstream/version-2.7
        $q = $dbc->prepare("SELECT charges,transNum,payments,
                year(tdate) AS year, month(tdate) AS month, day(tdate) AS day
                FROM $fromSpec AS s 
                WHERE s.cardNo=? AND programID=?
                ORDER BY tdate DESC");
        $args=array($this->cardNo,$this->programID);
        $r = $dbc->execute($q,$args);
        END >>>>>>> upstream/version-2.7
 */

        $data = array();
        $rrp  = "{$FANNIE_URL}admin/LookupReceipt/RenderReceiptPage.php";
        $balance = 0.0;
        $opening = array();
        $lastRow = array();
        while($row = $dbc->fetchRow($r)) {
            if ($row['charges'] == 0 && $row['payments'] == 0) {
                continue;
            }
            /* Possibly context from previous record
             */

            /* A Reimbursement-of-Bank pair:
             *  First: non-bank, .payments is negative
             *  Second: bank, .payments is positive, same absolute amount.
             * Compare details of this item to the previous one to see
             * If it is the second of a Reimbursement-of-Bank pair:
             * - isBank and previous isn't
             * - Same day
             * - Same emp and reg, next trans
             * - 'payment' inverse of previous
             */
            $reimburseBank = 0;
            if ($row['cardNo'] == $this->programBankID && !empty($lastRow)) {
                list($emp,$reg,$trans) = explode('-',$row['transNum']);
                list($lastEmp,$lastReg,$lastTrans) = explode('-',$lastRow['transNum']);
                if (
                    $lastRow['cardNo'] != $this->programBankID &&
                    $row['payments'] > 0 &&
                    (($row['payments'] + $lastRow['payments']) == 0) &&
                    (substr($row['tdate'],0,10) == substr($lastRow['tdate'],0,10)) &&
                    $emp == $lastEmp &&
                    $reg == $lastReg &&
                    $trans == ($lastTrans + 1)
                ) {
                    $reimburseBank = 1;
                }
            }
            //
            if ($row['cardNo'] != $this->cardNo) {
                $lastRow = $row;
                continue;
            }
            $record = array();
            $record[] = $row['ddate'];
            if (FormLib::get('excel') !== '') {
                $record[] = $row['transNum'];
            } else {
                // Receipt#, linked to Receipt Renderer, new tab
                $record[] = sprintf("<a href='{$rrp}?year=%d&month=%d&day=%d&receipt=%s' " .
                    "target='_CCRA_%s'" .
                    ">%s</a>",
                    $row['year'],$row['month'],$row['day'],
                    $row['transNum'],
                    $row['transNum'],
                    $row['transNum']
                );
            }
            // Amount
            $record[] = sprintf('%.2f', ($row['charges'] != 0 ? (-1 * $row['charges']) : $row['payments']));
            $record[] = $this->getTypeLabel($row['charges'],$row['payments'],
                $this->cardNo,$this->programBankID,$this->programID,$reimburseBank);
            $balance += ($row['charges'] != 0 ? (-1 * $row['charges']) : $row['payments']);
            $record[] = sprintf('%.2f',$balance);
            /* If the start of the date range is later than programStart
             * and the current item dated earlier than that
             * set the running balance aside as opening balance
             * and when you get to the first date => the start of the range
             * display the opening balance and then the first wanted item.
             */
            if (($this->dateFrom > $this->programStartDate) &&
                ($row['ddate'] < $this->dateFrom)
            ) {
                $opening = $record;
                continue;
            }
            if (!empty($opening) && ($row['ddate'] >= $this->dateFrom)) {
                /* The year, month and day values have be be real, not 0.
                 * otherwise the JS sort sorts them at the end.
                 */
                $opening[0] = $this->dateFrom;
                $opening[1] = '--';
                $opening[2] = $opening[4];
                $opening[3] = 'Opening Balance';
                $data[] = $opening;
                $opening = array();
            }
            $data[] = $record;
            $lastRow = $row;
        }

        return $data;
    }

    public function calculate_footers($data)
    {
        // NB: Refund is a reverse Purchase, not Income.
        $incomeLabels = array('Earning','Input','Transfer Back',
            'Opening','Balance', 'Opening Balance',
            'Error: negative charges');
        $outgoLabels = array('Purchase','Refund',
            'Transfer Out','Distribution',
            'Error: positive charges');
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

        // Subtotal of Earnings/Inputs
        $ret[0] = "Subtotal:";
        $ret[1] = '--';
        $ret[2] = number_format($income,2);
        if ($this->cardNo == $this->programBankID) {
            $ret[3] = "Inputs";
        } else {
            $ret[3] = "Earnings";
        }
        $ret[4] = '--';
        $footers[] = $ret;

        // Subtotal of Purchases/Disbursements
        $ret[0] = "Subtotal:";
        $ret[1] = '--';
        $ret[2] = number_format($outgo,2);
        if ($this->cardNo == $this->programBankID) {
            $ret[3] = "Distributions";
        } else {
            $ret[3] = "Purchases";
        }
        $ret[4] = '--';
        $footers[] = $ret;

        // Subtotal of the un-accounted-for.
        $total = 0.0;
        $total = ($income + $outgo);
        $net = ($total - $balance);
        if ($net < -0.01 || $net > 0.01) {
            $ret[0] = "Subtotal:";
            $ret[1] = '--';
            $ret[2] = number_format($net,2);
            $ret[3] = '--';
            $ret[3] = "Un-accounted for";
            $ret[4] = '--';
            $footers[] = $ret;
        }

        if ($balance > -0.01) {
            $balance = ($balance < 0)?0:$balance;
            $balanceColour = "black";
            // Probably program-dependent.
            if ($this->cardNo == $this->programBankID) {
                $balanceLabel = "Available for<br />distribution:";
            } else {
                $balanceLabel = "Available for<br />purchases:";
            }
        } else {
            $balanceColour = "red";
            if ($this->cardNo == $this->programBankID) {
                $balanceLabel = "Has over-<br />distributed:";
            } else {
                $balanceLabel = "Owes the<br />Coop:";
            }
        }
        $ret[0] = "<span style='color:{$balanceColour};'>" .
            $balanceLabel . "</span>";
        $ret[1] = '--';
            //sprintf("%0.2f",$balance)
        $ret[2] = "<span style='color:{$balanceColour};'>" .
            number_format($balance, 2) . "</span>";
        $ret[3] = '--';

        $footers[] = $ret;
        return $footers;
    }

    /* Return the appropriate label for the amount.
     * Needs to be externally configurable, in CCredPrograms,
     * but until then treat all programs the same.
     */
    private function getTypeLabel ($charges, $payment, $memberNumber, $bankNumber,
        $programID, $reimburseBank=0) {
        $label = "None";
        if ($memberNumber != $bankNumber) {
            $label = "p: $payment c: $charges";
            if ($charges > 0) {
                $label = 'Purchase';
            } elseif ($payment > 0) {
                $label = 'Earning';
            } elseif ($payment < 0) {
                $label = 'Transfer Out';
            } elseif ($charges < 0) {
                $label = 'Refund';
            } else {
                $label = 'No Movement';
            }
        } else {
            if ($payment < 0) {
                $label = "Distribution";
            } elseif ($payment > 0) {
                if ($reimburseBank) {
                    $label = "Transfer Back";
                } else {
                    $label = "Input";
                }
            } elseif ($charges > 0) {
                $label = "Error: positive charges";
            } elseif ($charges < 0) {
                $label = "Error: negative charges";
            } else {
                $label = 'No Movement';
            }
        }
        return $label;
    }

    /** The form for specifying the report
     */
    function form_content(){

        global $FANNIE_PLUGIN_SETTINGS, $FANNIE_OP_DB;

        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['CoopCredDatabase']);
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

<!-- Bootstrap-coded begins $_SERVER['PHP_SELF']-->
<form method = "get" action="ActivityReport.php" class="form-horizontal">
<div class="row">
    <div class="col-sm-6">
        <div class="form-group">
            <label class="col-sm-3 control-label">Member<br />in Program</label>
            <div class="col-sm-8">
<?php
            $OP = "{$FANNIE_OP_DB}{$dbc->sep()}";
            $query = "SELECT c.FirstName, c.LastName, m.programID, m.cardNo
                FROM CCredMemberships AS m
                LEFT JOIN {$OP}custdata AS c on m.cardNo = c.CardNo
                WHERE c.personNum =1
                ORDER BY programID, LastName, FirstName";
            $statement = $dbc->prepare("$query");
            $args = array();
            $results = $dbc->execute("$statement", $args);
            echo "<select id='memNum' name='memNum' size=8>";
            echo "<option value=''>Choose a Member in a Program</option>";
            while ($row = $dbc->fetchRow($results)) {
                $desc = sprintf('(%d) %s, %s',
                    $row['programID'],
                    $row['LastName'],
                    $row['FirstName']
                );
                printf("<option value='%d|%d'>%s</option>",
                    $row['programID'],
                    $row['cardNo'],
                    $desc
                );
            }
            echo "</select>";
?>
                <p class="explain" style="float:none; margin:0 0 0 0.5em;">
<span style='font-weight:bold;'>Choose a Member of a Program from the list above.
</span>
<br/>The (1) refers to the Program in the list below.
</p>
            </div><!-- /.col-sm-8 -->
        </div><!-- /.form-group -->

        <div class="form-group">
            <label class="col-sm-3 control-label">Key to Programs</label>
            <div class="col-sm-8">
<?php

            $selectSize = 1;
            $rslt = $dbc->query("SELECT count(programID) AS ct FROM CCredPrograms");
            if ($rslt !== False) {
                $row = $dbc->fetchRow($rslt);
                $selectSize = min(12,(int)$row['ct']);
            }
            echo "<select id='programID' name='programID' size='{$selectSize}'>";
            echo "<option value='-1' SELECTED>Key to Program Numbers</option>";
            $ccpModel = new CCredProgramsModel($dbc);
            $today = date('Y-m-d');
            foreach($ccpModel->find() as $prog) {
                $desc = $prog->programName();
                if ($prog->active()==0) {
                    $desc .= " (inactive)";
                }
                if ($prog->startDate() > $today) {
                    $desc .= " (Starts {$prog->startDate()})";
                }
                if ($prog->endDate() != 'NULL' && 
                    $prog->endDate() != '0000-00-00' &&
                    $prog->endDate() < $today) {
                    $desc .= " (Ended {$prog->endDate()})";
                }
                printf("<option value='%d'>(%d) %s</option>",
                    $prog->programID(),
                    $prog->programID(),
                    $desc
                );
            }
            echo "</select>";
?>
                <p class="explain" style="float:none; margin:0 0 0 0.5em;">
<span style='font-weight:normal;'>(No need to choose from the list of Programs above.)
</span>
</p>
            </div><!-- /.col-sm-8 -->
        </div><!-- /.form-group -->
    </div><!-- /.col-sm-6 end of left col -->
    <div class="col-sm-5"><!-- start right col -->

        <div class="form-group">
            <label class="col-sm-3 control-label">Start Date</label>
            <div class="col-sm-4">
                <input type=text id=date1 name=date1 class="form-control date-field" /><!-- required / -->
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">End Date</label>
            <div class="col-sm-4">
                <input type=text id=date2 name=date2 class="form-control date-field" /><!-- required / -->
            </div>
        </div>
        <div class="form-group">
            <!-- label class="col-sm-4 control-label"> </label -->
            <div class="col-sm-12">
                <p class="explain" style="float:none; margin:0 0 0 0.5em;">
<span style='font-weight:bold;'>Leave both dates empty to report on all the activity of the member.</span>
<br/>Leave Start date empty to report from the beginning of the program.</p>
            </div>
        </div><!-- /.form-group -->
        <div class="form-group">
<?php
            echo FormLib::date_range_picker();
?>                            
        </div>
    </div><!-- /.col-sm-5 end right col -->
</div><!-- /.row -->

<div class="row">
    <div class="col-sm-6"><!-- start left col -->
        <div class="form-group">
            <label for="sortable" class="col-sm-3 control-label"
title="Tick to display with sorting from column heads; un-tick for a plain formt."
>Sort on Column Heads</label>
            <input type="checkbox" name="sortable" id="sortable" CHECKED />
        </div>
    </div><!-- /.col-sm-6 end of left col -->

    <div class="col-sm-5"><!-- start right col -->
        <div class="form-group">
<?php
            echo "<!-- p>Right Col </p -->";
?>                            
        </div>
    </div><!-- /.col-sm-5 end right col -->
</div><!-- /.row -->
<p>
        <button type=submit name=submit value="Create Report" class="btn btn-default">Create Report</button>
        <!-- button type=reset name=reset class="btn btn-default">Start Over</button -->
</p>
            <!-- input type=hidden name=card_no id=card_no value=0  / -->
			<input type=hidden name='reportType' id='reportType' value='summary' />
			<input type=hidden name='subTotals' id='subTotals' value='0' />
			<input type=hidden name='dbSortOrder' id='dbSortOrder' value='ASC' />
</form><!-- /.form-horizontal -->

<!-- Bootstrap-coded ends -->
<?php
    // /form_content()
    }


    /**
      User-facing help text explaining how to 
      use a page.
      @return [string] html content
    */
    public function helpContent()
    {
        $help = "";
        $help .= "<p>" .
            "Earnings and Purchases of a single member of a program, inlcuding
            Running Balance, Subtotals and Balance.
            <br />Use this report for a summary of the Member of a Program.
            <br />From there you can drill down to receipts." .
            "</p>" .
            "<p>It is only necessary to choose from the 'Member in Program' List.
            <br />The number in parentheses refers to the Program in the 'Key to Programs' List.
            <br />A person may be a Member of more than one Program." .
            "</p>" .
            "<p>It is not necessary to choose from the 'Key to Programs' List." .
            "</p>" .
            "";
        return $help;
    }

}

FannieDispatch::conditionalExec();

