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

class PatronageByOwnerRange extends FannieReportPage 
{
    public $description = '[Patronage Over Date Range] lists top, or all, customers by purchases/avg basket over a range of dates';
    public $report_set = 'Membership';
    public $themed = true;

    protected $report_headers = array();
    protected $sort_direction = 1;
    protected $sort_column = 1;
    protected $title = "";
    protected $header = "";
    protected $required_fields = array('date1','date2');
    protected $top_n = 0;

    public function preprocess()
    {
        $this->report_headers = array(_('Member'), '$Total Purchased', '$Average per Receipt',
            '#Receipts');
        $this->title = "Fannie : Patronage by " . _('Member') . " over Date Range Report";
        $this->header = "Patronage by " . _('Member') . " over Date Range Report";
        if (is_numeric(FormLib::get_form_value('top_n',0))) {
            $this->top_n = FormLib::get_form_value('top_n',0);
        }

        if (FormLib::get_form_value('date1') !== ''){
            $this->content_function = "report_content";

            /**
              Check if a non-html format has been requested
               from the links in the initial display, not the form.
            */
            if (FormLib::get_form_value('excel') !== '') {
                $this->report_format = FormLib::get_form_value('excel');
                $this->has_menus(False);
            }
        } else {
			$this->add_script("../../src/CalendarControl.js");
        }

        return True;
    }

    /* Text at the top of the report, below the standard heading.
     */
    public function report_description_content()
    {
		$d1 = FormLib::get_form_value('date1');
		$d2 = FormLib::get_form_value('date2');
        if ($d2 == '') {
            $d2 = $d1;
        }
        $desc = array();
        $asAt = ($d2 == date('Y-m-d')) ?
            "<h3>" . _("As at: ") . date("l, F j, Y g:i A") . "</h3>" :
            '';
        list($year,$month,$day) = explode('-',$d1);
        $fromDate = date("l, F j, Y", mktime(0,0,0,$month,$day,$year));
        list($year,$month,$day) = explode('-',$d2);
        $toDate = date("l, F j, Y", mktime(0,0,0,$month,$day,$year));
        $desc[] = sprintf("<h2>%s%s</h2>%s",
            ($this->window_dressing) ? '' : $this->header.'<br />',
            ($d1 == $d2) ? _("For ").$fromDate : _("From ").$fromDate._(" to ").$toDate,
            "$asAt");
        $line = "<p><b>Patronage over Date Range: " .
        (($this->top_n > 0) ? "Top " . $this->top_n : "All") .
            " Members, by Total Purchases</b></p>";
        $desc[] = $line;
        //$line = "Var: " . $this->top_n . "  Form:" .  FormLib::get_form_value('top_n',0);
        $line = "<a href='" . $_SERVER['PHP_SELF'] ."'>Start over</a>";
        $desc[] = $line;
        return $desc;
    }

    function fetch_report_data(){
        global $FANNIE_OP_DB, $FANNIE_COOP_ID,
             $FANNIE_TRANS_DB, $FANNIE_URL;

        $d1 = FormLib::get_form_value('date1',date('Y-m-d'));
        $d2 = FormLib::get_form_value('date2',date('Y-m-d'));

        //$dbc = FannieDB::get($FANNIE_OP_DB);

        $dlog = DTransactionsModel::select_dlog($d1,$d2);

        if ( isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto' ) {
            $shrinkageUsers = " AND (card_no not between 99900 and 99998)";
            /* Keeps out both inputs and corrections.
            $coopCredDepartments = " AND (department != 9800) " .
            "AND (department NOT BETWEEN 1021 AND 1040)";
             */
            $coopCredDepartments = "";
        } else {
            $shrinkageUsers = "";
            $coopCredDepartments = "";
        }

        $card_no = array();
        $id = array();   
        $total = array();       // Total Spent for desired Range
        $avg = array();         // Average Basket
        $numTran = array();     // Number of transactions for selected Range for each Owner
        
        $dbc = FannieDB::get($FANNIE_TRANS_DB);
        
        $limit = "";
        if ($this->top_n) {
            //limit {$_POST['top_n']}
            $limit = " LIMIT " . $this->top_n;
        }
        /* Total purchases for each member.
         * Why is it not summing .total?
         * Return parallel arrays $card_no of .card_no, $total of total.
         * Better array of arrays: $x["$card_no"] = array($total,$trans_count)
         *   $trans_count is 0, not known yet.
        $query = "SELECT card_no,
            sum(unitPrice*quantity) as UtimesQ  
            ,sum(unitPrice*quantity) as T  
                FROM $dlog dx
                WHERE
                (tdate BETWEEN ? AND ?)
                AND upc != 0{$shrinkageUsers}
                GROUP BY card_no 
                ORDER BY UtimesQ desc{$limit}
                ;";
         */
        $query = "SELECT card_no,
                sum(total) as T  
                FROM $dlog dx
                WHERE
                (tdate BETWEEN ? AND ?)
                AND trans_type in ('I','D','S'){$shrinkageUsers}{$coopCredDepartments}
                GROUP BY card_no 
                ORDER BY T desc{$limit}
                ;";

$dbc->logger("First: $query");
        $statement = $dbc->prepare_statement($query);
        $args = array($d1.' 00:00:00', $d2.' 23:59:59');
        $result = $dbc->exec_statement($statement, $args);
        $mdata = array(); // New
        while ($row = $dbc->fetch_row($result)) {
            $card_no[] = $row['card_no'];
            $total[] = $row['T'];
            //New
            $mem_num = $row['card_no'];
            $mdata["$mem_num"] = array($row['T'],0);
        }     

        /* Number of transactions for each member in the previous search.
         * OK to do this way if $top_n <= ~10, but not for all.
         * Why all the other arguments? Needed to distinguish transactions: emp-reg-trans on y-m-d.
         * Is there more than one upc=tax line per transaction? No.
         *  Use trans_type = 'A' instead.
         */
        $plan = '';
        if (true && ($this->top_n > 0 && $this->top_n <= 10)) {
            $plan = 'A';
            $query = "SELECT trans_num, month(tdate) as mt, day(tdate) as dt, year(tdate) as yt 
                    FROM $dlog  dx
                    WHERE
                    (tdate BETWEEN ? AND ?)
                    AND card_no = ? AND trans_type = 'A'
                    GROUP BY trans_num, mt, dt, yt 
                    ORDER BY mt, dt, yt
                    ;";
$dbc->logger("Second {$plan}: $query");
            $statement = $dbc->prepare_statement($query);
            $args = array($d1.' 00:00:00', $d2.' 23:59:59');
            for($i=0; $i<count($card_no);$i++) {
                $args[2] = $card_no[$i];
                $result = $dbc->exec_statement($statement, $args);
                //$result = $dbc->query($query);
                // Why not use $dbc->row_count($result); ?
                $count = 0;
                while ($row = $dbc->fetch_row($result)) {
                    $count++;
                }
                $numTran[] = $count;
                //New
                $mdata["$card_no[$i]"][1] = $count;
            }
        } elseif (false) {
            $plan = 'B';
            /* A row for each receipt
                Aggregate not possible.
                sum(CASE WHEN upc = 'tax' THEN 1 ELSE 0 END) as ts,
                    $mdata["$mem_num"][1] += $row['ts'];
            ----
                    ,month(tdate) as mt, day(tdate) as dt, year(tdate) as yt 
                    GROUP BY card_no, trans_num, mt, dt, yt 
                    ORDER BY card_no, mt, dt, yt
             * */
            $query = "SELECT card_no, trans_num
                    FROM $dlog  dx
                    WHERE
                    (tdate BETWEEN ? AND ?)
                    AND (trans_type = 'A'){$shrinkageUsers}
                    ;";
$dbc->logger("Second {$plan}: $query");
            $statement = $dbc->prepare_statement($query);
            $args = array($d1.' 00:00:00', $d2.' 23:59:59');
            $result = $dbc->exec_statement($statement, $args);
            //$result = $dbc->query($query);
            $count = 0;
            while ($row = $dbc->fetch_row($result)) {
                $mem_num = $row['card_no'];
                $mdata["$mem_num"][1] += 1;
                $count++;
            }
        } else {
            $plan = 'C';
            /*
            $query = "SELECT count(card_no) AS ct, card_no, trans_num
                    FROM $dlog  dx
                    WHERE
                    (tdate BETWEEN ? AND ?)
                    AND (trans_type = 'A'){$shrinkageUsers}
                    GROUP BY card_no, trans_num
                    ;";
             */
            $query = "SELECT count(card_no) AS ct, card_no
                    FROM $dlog  dx
                    WHERE
                    (tdate BETWEEN ? AND ?)
                    AND (trans_type = 'A'){$shrinkageUsers}
                    GROUP BY card_no
                    ;";
$dbc->logger("Second {$plan}: $query");
            $statement = $dbc->prepare_statement($query);
            $args = array($d1.' 00:00:00', $d2.' 23:59:59');
            $result = $dbc->exec_statement($statement, $args);
            $count = 0;
            while ($row = $dbc->fetch_row($result)) {
                $mem_num = $row['card_no'];
                $mdata["$mem_num"][1] = $row['ct'];
                $count++;
            }
        }

        // Compose the rows of the report in a 2D array.
$dbc->logger("Compose");
        $info = array();
        if ($plan == 'A') {
        for($i=0; $i<count($card_no);$i++) {
            //$card_no[$i] . ' ' . $plan . ' ' . $count,
            $table_row = array(
                $card_no[$i],
                sprintf("%0.2f", $total[$i]),
                sprintf("%0.2f", ($total[$i] / $numTran[$i])),
                $numTran[$i],
            );
            $info[] = $table_row;
        }
        }
        //New
        if ($plan == 'B' || $plan == 'C') {
        foreach ($mdata as $mem => $mbits) {
            //$mem . ' ' . $plan . ' ' . $count,
            $table_row = array(
                $mem,
                sprintf("%0.2f", $mbits[0]),
                sprintf("%0.2f", ($mbits[0] / $mbits[1])),
                $mbits[1],
            );
            $info[] = $table_row;
        }
        }
        
        return $info;


    // fetch_report_data()
    }

    /**
      Sum the quantity and total columns for a footer of one or more rows.
      Also set up headers and in-table (column-head) sorting.
    */
    function calculate_footers($data)
    {
        // no data; don't bother
        if (empty($data)) {
            return array();
        }

        /* Initial sequence of the report.
         * May not be the same as the sequence  of composition, driven, say,
         *  by an ORDER BY clause.
         */
        $this->sort_column = 1; // First = 0
        $this->sort_direction = 1;  // 1 = ASC

        $sumSales = 0.0;
        $sumAvgBskt = 0.0;
        $sumTransactions = 0;
        foreach($data as $row) {
            $sumSales += $row[1];
            $sumAvgBskt += $row[2];
            $sumTransactions += $row[3];
        }
        $rowCount = count($data);
        $avgBskt = sprintf("%0.2f", ($sumSales / $sumTransactions));
        //$avgAvgBskt = sprintf("%0.2f", ($sumAvgBskt / $rowCount));
        $avgBskts = sprintf("%0.2f", ($sumTransactions / $rowCount));
        /* Means? */

        return array(
            array('#Members','$ Grand Total Purchases','$ Overall Average per Receipt',
            '# Grand Total Receipts'),
            array(number_format($rowCount),
            '$ '.number_format($sumSales,2),
            '$ '.number_format($avgBskt,2),
            number_format($sumTransactions)
            ),
            array('','','Average Receipts per Member',''),
            array('','',number_format($avgBskts,2),'')
        );

    // calculate_footers()
    }


    function form_content(){
        $lastMonday = "";
        $lastSunday = "";

        $ts = mktime(0,0,0,date("n"),date("j")-1,date("Y"));
        while($lastMonday == "" || $lastSunday == ""){
            if (date("w",$ts) == 1 && $lastSunday != "")
                $lastMonday = date("Y-m-d",$ts);
            elseif(date("w",$ts) == 0)
                $lastSunday = date("Y-m-d",$ts);
            $ts = mktime(0,0,0,date("n",$ts),date("j",$ts)-1,date("Y",$ts));    
        }
        ?>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get" id="form1">
		<table cellspacing=4 cellpadding=4 border=0>
		<tr>
        <th>
            <label>Top how many <?php echo _("members"); ?>?
            <br />(Leave empty for all.)</label>
</th>
<td>
                <input type="text" name="top_n" value="" class="form-control"
                    id="top_n" />
</td>
</tr>
		<tr>
		<th>Start Date</th>
		<td><input type=text id=date1 name=date1 onclick="showCalendarControl(this);"
            value="<?php echo $lastMonday; ?>" /></td>
		<td rowspan="2">
		<?php echo FormLib::date_range_picker(); ?>
		</td>
        </tr>
        <tr>
		<th>End Date</th>

        <td><input type=text id=date2 name=date2 onclick="showCalendarControl(this);"
            value="<?php echo $lastSunday; ?>" /></td>
        </tr>

        <tr>
        <td>
		Excel (CSV) <input type=checkbox name=excel value="csv" />
		<!-- &nbsp; Xls <input type=checkbox name=excel value="xls" / -->
        </td>
        <td> &nbsp;
        </td>
        </tr>

        <tr>
        <td>
                    <button type="submit" class="btn btn-default">Prepare Report</button>
        </td>
        <td> &nbsp;
        </td>
        </tr>

</table>
        </form>
        <?php
        $this->add_onload_command('$(\'#top_n\').focus()');

    // form_content()
    }


    public function helpContent()
    {
        return '<p>
            List ' . _("members") . ' by total purchases over a range of dates.
            Can choose to show the Top (highest dollar-value) <i>N</i> or all
            who shopped at all.
            </p>';
    }

}

FannieDispatch::conditionalExec();

