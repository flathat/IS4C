<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

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
   - As plugin.
   - Not hard-coded membership department. Plugin var?
   - Error: 'Every derived table must have its own alias.'
 */

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class MembershipEntryReport extends FannieReportPage {

    private $memtypes;
    protected $title = "Fannie : Membership Entry Report";
    protected $header = "Membership Entry Report";
    protected $required_fields = array('date1', 'date2');

    public $description = '[Membership Entry] compares the number of memberships
        purchased to the number entered in the database over a period of time';
    public $themed = true;
    public $report_set = 'Membership';

    function preprocess()
    {

        $this->report_headers[] = 'Year';
        $this->report_headers[] = 'Month';
        $this->report_headers[] = 'Purchased';
        $this->report_headers[] = 'Entered';

        return parent::preprocess();
    }

    function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $date1 = $this->form->date1;
        $date2 = $this->form->date2;

        /* Memberhips purchased */
        $dlog = DTransactionsModel::selectDlog($date1,$date2);
        $date1 .= ' 00:00:00';
        $date2 .= ' 23:59:59';
        // Error: 'Every derived table must have its own alias.'
        $dlog = 'trans_archive.dlogBig';

        $purchasedQ = "SELECT 
            year( tdate ) AS Year ,
            month( tdate ) AS Month ,
            count( card_no ) AS Count
            FROM $dlog
            WHERE tdate BETWEEN ? AND ?
            AND department =1000
            GROUP BY year (tdate), month( tdate )
            ORDER BY year (tdate), month( tdate )";

        $purchasedS = $dbc->prepare($purchasedQ);
        $result = $dbc->execute($purchasedS, array($date1, $date2));

        /**
            For each month: Year, Month, Purchased, Entered.
            Purchased on first search, Entered on second.
            Need to allow for months absent due to 0 hits.
        */
        /* $ret is an array of row-arrays. */
        $ret = array();
        while ($row = $dbc->fetch_array($result)){
            $item = array();
            $item[] = $row['Year'];
            // Need to translate to Month-name
            $item[] = $row['Month'];
            $item[] = $row['Count'];
            $item[] = 0; // placeholder for number-Entered
            $ret[] = $item;
        }

        $enteredQ = "SELECT 
            year( start_date ) AS Year ,
            month( start_date ) AS Month ,
            count( card_no ) AS Count
            FROM memDates
            WHERE start_date BETWEEN ? AND ?
            GROUP BY year (start_date), month( start_date )
            ORDER BY year (start_date), month( start_date )";

        $enteredS = $dbc->prepare($enteredQ);
        $result = $dbc->execute($enteredS, array($date1, $date2));
        $retPtr = 0;
        while ($row = $dbc->fetch_array($result)){
            if (
                $row['Year'] == $ret[$retPtr][0] &&
                $row['Month'] == $ret[$retPtr][1]
            ) {
                $ret[$retPtr][3] = $row['Count'];
            }
            $ret[$retPtr][1] = date("M",mktime(0,0,0,$ret[$retPtr][1],1,$ret[$retPtr][0]));
            $retPtr++;
        }

        return $ret;
    }
    
    /**
      Sum the Purchased and Entered columns
    */
    function calculate_footers($data){
        $totalPurchased = 0;
        $totalEntered = 0;
        foreach($data as $row){
            $totalPurchased += $row[2];
            $totalEntered += $row[3];
        }
        $ret = array('Totals','');
        $ret[] = number_format($totalPurchased);
        $ret[] = number_format($totalEntered);

        return $ret;
    }

    function form_content()
    {
        list($lastMonday, $lastSunday) = \COREPOS\Fannie\API\lib\Dates::lastWeek();
        ob_start();
?>
    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method=get>
<div class="col-sm-6">
<p>
    <label>Start Date</label>
    <input type=text id=date1 name=date1 value="<?php echo $lastMonday; ?>"
        class="form-control date-field" required />
</p>
<p>
    <label>End Date</label>
    <input type=text id=date2 name=date2 value="<?php echo $lastSunday; ?>" 
        class="form-control date-field" required />
</p>
<p>
    <button type=submit name=submit value="Submit" class="btn btn-default">Submit</button>
    <label><input type=checkbox name=excel id="excel" value=xls /> Excel</label>
</p>
</div>
<div class="col-sm-6">
    <?php echo FormLib::dateRangePicker(); ?>
</div>
</form>
<?php
        return ob_get_clean();
    }

    public function helpContent()
    {
        $ret = '';
        $ret .= '<p>' .
            'This report lists the number of memberships purchased
            and added to the database for a period of time.
            It assumes membership start date is the date of purchase.
            It is a rough comparison of how entry of memberships into the
            database is keeping up with purchases at cash.' .
            '</p>';
        return $ret;
    }
}

FannieDispatch::conditionalExec();

