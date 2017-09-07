<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op
    Copyright 2015 River Valley Market, Northampton, MA

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
/* TODO
 * Append "Thu Apr 30" to the date column
 */

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class SuspendedTransactionsReport extends FannieReportPage 
{

    protected $title = "Fannie : Suspended Transactions";
    protected $header = "Suspended Transactions Report";
    protected $report_headers = array('Date', 'Cashier#', 'Lane#', 'Receipt',
        'Amount', 'Mem#', 'Member Name');
    protected $required_fields = array('date1', 'date2');
    protected $report_cache = 'none';

    public $description = '[Suspended Transactions] lists transactions suspended
        during a given date range';
    public $report_set = 'Transactions';
    public $themed = true;

    function fetch_report_data()
    {
        global $FANNIE_OP_DB, $FANNIE_TRANS_DB, $FANNIE_URL;
        $dbc = FannieDB::get($FANNIE_TRANS_DB);
        $op = $FANNIE_OP_DB . $dbc->sep();
        $date1 = FormLib::get_form_value('date1',date('Y-m-d'));
        $date2 = FormLib::get_form_value('date2',date('Y-m-d'));
        $empNo = FormLib::get_form_value('emp_no','0');

        $args = array($date1 . ' 00:00:00', $date2 . ' 23:59:59');
        $query = "SELECT MAX(s.datetime) AS datetime,
                    s.emp_no,
                    s.register_no,
                    CONCAT_WS('-',CAST(s.emp_no AS CHAR),CAST(s.register_no AS CHAR),CAST(s.trans_no AS CHAR)) AS receipt,
                    SUM(s.total) AS amt,
                    s.card_no,
                    c.LastName,
                    c.FirstName
                  FROM suspended AS s
                    LEFT JOIN {$op}custdata AS c ON s.card_no=c.CardNo AND c.personNum=1
                  WHERE s.datetime BETWEEN ? AND ?";
        if ($empNo != 0) {
            $args[] = $empNo;
            $query .= ' AND s.emp_no = ? ';
        }
        $query .= ' GROUP BY year(s.datetime), month(s.datetime), day(s.datetime), s.emp_no, s.register_no, s.trans_no';
        $prep = $dbc->prepare($query);
        $rslt = $dbc->execute($prep, $args);

        $data = array();
        $record = array();
        $rrp  = "{$FANNIE_URL}admin/LookupReceipt/RenderReceiptPage.php";
        $isExcel = FormLib::get('excel','');
        $numRows = $dbc->num_rows($rslt);
        //while($row = $dbc->fetchArray($rslt)){ // Why does this only get half the rows?
        for($i=0;$i<$numRows;$i++) {
            $row = $dbc->fetchArray($rslt);
            $record = array();
            $record[] = $row['datetime'];
            $record[] = $row['emp_no'];
            $record[] = $row['register_no'];
            if ($isExcel != '') {
                $record[] = $row['receipt'];
            } else {
                list($rY,$rM,$rD) = explode('-',substr($row['datetime'],0,10));
                // Receipt#, linked to Receipt Renderer
                $record[] = sprintf("<a href='{$rrp}?year=%d&month=%d&day=%d&receipt=%s&table=%s' %s>%s</a>",
                    $rY,$rM,$rD,$row['receipt'],
                    "suspended",
                    "target='_blank'",
                    $row['receipt']);
            }

            $record[] = sprintf('%.2f', $row['amt']);
            $record[] = $row['card_no'];
            if ($row['card_no'] == 0) {
                $record[] = 'Not known';
            } else {
                $record[] = $row['LastName'] . ', ' . $row['FirstName'];
            }
            $data[] = $record;

        }

        return $data;
    }

    /**
        Extra, non-tabular information prepended to reports.
        In addition to Title/Heading and  Date Range.
        If for a single cashier then name of Cashier
      @return array of strings
    */
    function report_description_content()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $cashier = 'Any Cashier';
        $empNo = FormLib::get('emp_no', 0);
        if ($empNo != 0) {
            $emp = new EmployeesModel($dbc);
            $emp->emp_no($empNo);
            if ($emp->load()) {
                $cashier = sprintf("%d %s %s",
                    $emp->emp_no(),
                    $emp->FirstName(),
                    $emp->LastName());
            } else {
                $cashier = 'Unknown?';
            }
        }
        return array(
            'Suspensions by: ' . $cashier
        );
    }
    
    function form_content()
    {
        global $FANNIE_OP_DB, $FANNIE_URL;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $query = "SELECT emp_no, FirstName, LastName " .
            "FROM employees ORDER BY LastName, FirstName";
        $statement = $dbc->prepare($query);
        $rslt = $dbc->execute($statement,array());
        /*
         * 2 columns
         * Left:
         *  Date Start
         *  Date End
         *  Cashier
         *  Submit(s)
         * Right:
         *  Date Picker
*/
?>
<form method = "get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
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
        <label>Cashier</label>
        <select name="emp_no" class="form-control">
            <option value="0">All Cashiers</option>
            <?php while($row = $dbc->fetchArray($rslt)) {
                printf('<option value="%d">%d %s, %s</option>',
                        $row['emp_no'],
                        $row['emp_no'],
                        $row['LastName'],
                        $row['FirstName']
                );
            } ?>
        </select>
    </div>
    <!-- div class="form-group"> 
        <input type="checkbox" name="excel" id="excel" value="xls" />
        <label for="excel">Excel</label>
    </div -->
    <div class="form-group"> 
        <button type=submit name=submit value="Submit"
            class="btn btn-default">Create Report</button>
        <!-- button type=reset name=reset value="Start Over"
            class="btn btn-default">Start Over</button -->
    </div>
</div>
<!-- Right column -->
<div class="col-sm-5">
    <?php echo FormLib::date_range_picker(); ?>
</div>
</form>
<?php
    }
}

FannieDispatch::conditionalExec(false);

?>
