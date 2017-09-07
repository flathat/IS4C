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

class GeneralDayDiscountReportMP extends FannieReportPage 
{
    public $description = '[General Day Discount Report MP] lists tenders, sales,
        discounts, and taxes for a given day
        with Sales reflecting transaction-level Discounts
        (should net to zero).
        Also listed are transaction count &amp; size information by member type and
        equity sales for the day.
        Shows Sales per Membership Type';
    public $report_set = 'Sales Reports';
    public $themed = true;
    protected $new_tablesorter = true;

    protected $title = "Fannie : General Day Discount Report MP";
    protected $header = "General Day Discount Report - Membership Percentage";
    protected $report_cache = 'none';
    protected $grandTTL = 1;
    protected $multi_report_mode = true;
    protected $sortable = false;
    protected $no_sort_but_style = true;
    protected $shrinkageUsers = "";

    protected $report_headers = array('Desc','Qty','Amount');
    protected $required_fields = array('date');
    protected $sales_reflect_discounts = false;
    protected $tProportion = 0.0;

    /* #'p
        if (FormLib::get_form_value('sales_reflect_discounts',false)) {
            $this->sales_reflect_discounts = true;
        }
     * */
    function preprocess()
    {
        parent::preprocess();
        $this->sales_reflect_discounts =
            FormLib::get_form_value('sales_reflect_discounts',false);
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $this->shrinkageUsers = " AND (d.card_no not between 99900 and 99998)";
        }
        return true;
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
		$ret = array();
        $ret[] = "<p class='explain'><a href='" . $_SERVER['PHP_SELF'] . "'>Start over</a>" .
                "" .
                "</p>";
        if ($this->sales_reflect_discounts) {
            $ret[] ="<p class='explain'>Sales figures are reduced by " .
                "percentages of Whole-transaction Discounts " .
                "as well as per-item discounts and sale prices." .
                "<br />The Discounts section shows the totals of Whole-transaction Discounts." .
                "<br />The Reconciliation section shows zero for Discounts because they are " .
                "already reflected in Sales." .
                "" .
                "</p>";
        } else {
            $ret[] ="<p class='explain'>Sales figures are not reduced by " .
                "percentages of Whole-transaction Discounts " .
                "but do reflect per-item discounts and sale prices." .
                "" .
                "</p>";
        }
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            if ($this->shrinkageUsers !== "") {
            $ret[] ="<p class='explain'>This report excludes West End Food Co-op In-house users." .
                "" .
                "</p>";
            }
        }
        $ret[] ="";
		return $ret;
    // /report_description_content()
	}

	/**
	  Extra, non-tabular information appended to reports
	  @return array of strings
	*/
    function report_end_content(){
		$ret = array();
        $ret[] = "<p class='explain'><a href='" . $_SERVER['PHP_SELF'] . "'>Start over</a>" .
                "" .
                "</p>";
        //$ret[] ="";

		return $ret;
    // /report_description_content()
	}

    function fetch_report_data()
    {
        global $FANNIE_OP_DB, $FANNIE_ARCHIVE_DB, $FANNIE_EQUITY_DEPARTMENTS,
            $FANNIE_COOP_ID;
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $d1 = $this->form->date;
        $dates = array($d1.' 00:00:00',$d1.' 23:59:59');
        $data = array();

        $reconciliation = array(
            'Tenders' => 0.0,
            'Sales' => 0.0,
            'Discounts' => 0.0,
            'Tax' => 0.0,
        );

        /* Tenders */
        $dlog = DTransactionsModel::selectDlog($d1);
        $tenderQ = $dbc->prepare_statement("SELECT 
            TenderName,count(d.total),sum(d.total) as total
            FROM $dlog as d,
                {$FANNIE_OP_DB}.tenders as t 
            WHERE d.tdate BETWEEN ? AND ?
                AND d.trans_subtype = t.TenderCode
                AND d.total <> 0{$this->shrinkageUsers}
            GROUP BY t.TenderName ORDER BY TenderName");
        $tenderR = $dbc->exec_statement($tenderQ,$dates);
        $report = array();
        while($tenderW = $dbc->fetch_row($tenderR)){
            $record = array($tenderW['TenderName'],$tenderW[1],
                    sprintf('%.2f',$tenderW['total']));
            $report[] = $record;
            $reconciliation['Tenders'] += $tenderW['total'];
        }
        $data[] = $report;

        /* Sales
         * .department <> 0 keeps DISCOUNT and TAX out
         * */
        $salesQ = '';
        /* */
        if ($this->sales_reflect_discounts) {
            /* First
            $totalStatement = '
                CASE WHEN discountable = 1 AND percentDiscount > 0 THEN
                    SUM(ROUND(d.total * (1 - (percentDiscount / 100)),2))
                    ELSE SUM(d.total) END
                    AS total ';
              */
            /* Not quite as better: ROUND() makes it worse.
            $totalStatement = '
                SUM(
                CASE WHEN discountable = 1 AND percentDiscount > 0 THEN
                    ROUND(d.total * (1 - (percentDiscount / 100)),2)
                    ELSE d.total END)
                    AS total ';
             * */
            /* Best so far:
             * Perfect April 18, 19 $4-5K gross.
             * Off + $0.21 on $16K gross
             * */
            $totalStatement = '
                SUM(
                CASE WHEN discountable = 1 AND percentDiscount > 0 THEN
                    d.total * (1 - (percentDiscount / 100))
                    ELSE d.total END)
                    AS total ';
        } else {
            $totalStatement = '
                    SUM(d.total) AS total ';
        }
        /*
                    SELECT t.dept_name AS category,
                    SELECT m.super_name AS category,
         */
        switch (FormLib::get('sales-by')) {
            case 'Department':
                $salesQ = '
                    SELECT CASE WHEN COALESCE(t.dept_name,\'\') = \'\' THEN d.department
                    ELSE t.dept_name END AS category,
                        SUM(d.quantity) AS qty, ' .
                        $totalStatement . '
                    FROM ' . $dlog . ' AS d
                        LEFT JOIN departments AS t ON d.department=t.dept_no
                    WHERE d.department <> 0
                        AND d.trans_type <> \'T\' ' . $this->shrinkageUsers . '
                        AND d.tdate BETWEEN ? AND ?
                    GROUP BY t.dept_name
                    ORDER BY t.dept_name'; 
                break;
            case 'Sales Code':
                $salesQ = '
                    SELECT t.salesCode AS category,
                        SUM(d.quantity) AS qty, ' .
                        $totalStatement . '
                    FROM ' . $dlog . ' AS d
                        LEFT JOIN departments AS t ON d.department=t.dept_no
                    WHERE d.department <> 0
                        AND d.trans_type <> \'T\' ' . $this->shrinkageUsers . '
                        AND d.tdate BETWEEN ? AND ?
                    GROUP BY t.salesCode
                    ORDER BY t.salesCode'; 
                break;
            case 'Super Department':
            default:
                $salesQ = '
                    SELECT CASE WHEN COALESCE(m.super_name,\'\') = \'\' THEN d.department
                    ELSE m.super_name END AS category,
                        SUM(d.quantity) AS qty, ' .
                        $totalStatement . '
                    FROM ' . $dlog . ' AS d
                        LEFT JOIN MasterSuperDepts AS m ON d.department=m.dept_ID
                    WHERE d.department <> 0
                        AND d.trans_type <> \'T\' ' . $this->shrinkageUsers . '
                        AND d.tdate BETWEEN ? AND ?
                    GROUP BY m.super_name
                    ORDER BY m.super_name';
                break;
        }
        $salesP = $dbc->prepare($salesQ);
        $salesR = $dbc->exec_statement($salesP,$dates);
        $report = array();
        while($salesW = $dbc->fetch_row($salesR)){
            $record = array($salesW['category'],
                    sprintf('%.2f',$salesW['qty']),
                    sprintf('%.2f',$salesW['total']));
            $report[] = $record;
            $reconciliation['Sales'] += $salesW['total'];
        }
        $data[] = $report;

        /* Discounts */
        $discQ = $dbc->prepare_statement("SELECT m.memDesc, SUM(d.total) AS Discount,count(*)
                FROM $dlog d 
                    INNER JOIN memtype m ON d.memType = m.memtype
                WHERE d.tdate BETWEEN ? AND ?
                   AND d.upc = 'DISCOUNT'{$this->shrinkageUsers}
                AND total <> 0
                GROUP BY m.memDesc ORDER BY m.memDesc");
        $discR = $dbc->exec_statement($discQ,$dates);
        $report = array();
        while($discW = $dbc->fetch_row($discR)){
            $record = array($discW['memDesc'],$discW[2],$discW[1]);
            $report[] = $record;
            $reconciliation['Discounts'] += $discW['Discount'];
        }
        $data[] = $report;

        /* Tax: each type */
        $report = array();
        $trans = DTransactionsModel::selectDTrans($d1);
        $lineItemQ = $dbc->prepare("
            SELECT description,
                SUM(regPrice) AS ttl
            FROM $trans AS d
            WHERE datetime BETWEEN ? AND ?
                AND d.upc='TAXLINEITEM'
                AND " . DTrans::isNotTesting('d') . $this->shrinkageUsers .
                " GROUP BY d.description"
            );
//$dbc->logger("e: $lineItemQ" . print_r($dates,true));
        $lineItemR = $dbc->execute($lineItemQ, $dates);
        while ($lineItemW = $dbc->fetch_row($lineItemR)) {
            $record = array($lineItemW['description'] . ' (est. owed)',
                sprintf('%.2f', $lineItemW['ttl']));
            $report[] = $record;
        }

        /* Tax: total */
        $taxSumQ = $dbc->prepare_statement("SELECT  sum(total) as tax_collected
            FROM $dlog as d 
            WHERE d.tdate BETWEEN ? AND ?
                AND (d.upc = 'tax'){$this->shrinkageUsers}
            GROUP BY d.upc");
//$dbc->logger("t: $taxSumQ" . print_r($dates,true));
        $taxR = $dbc->exec_statement($taxSumQ,$dates);
        while($taxW = $dbc->fetch_row($taxR)){
            $record = array('Total Tax Collected',
                sprintf("%.2f",$taxW['tax_collected']));
            $report[] = $record;
            $reconciliation['Tax'] = $taxW['tax_collected'];
        }
        $data[] = $report;

        /* Reconciliation */
        $report = array();
        if ($this->sales_reflect_discounts) {
            $reconciliation['Discounts'] = 0;
        }
        foreach ($reconciliation as $type => $amt) {
            $report[] = array(
                $type,
                sprintf('%.2f', $amt),
            );
        }
        $data[] = $report;

        /* #'B Baskets: #transactions and #items */
        if ($this->sales_reflect_discounts) {
            $totalColumn = '
                CASE WHEN discountable = 1 AND percentDiscount > 0 THEN
                    (total * (1 - (percentDiscount / 100)))
                    ELSE total END
                    AS total';
        } else {
            $totalColumn = ' total';
        }
        $transQ = $dbc->prepare_statement("SELECT q.trans_num,
            sum(q.quantity) as items,
            transaction_type, sum(q.total)
            FROM
            (
            SELECT trans_num,card_no,quantity,{$totalColumn},
            m.memDesc as transaction_type
            FROM $dlog as d
            LEFT JOIN memtype as m on d.memType = m.memtype
            WHERE d.tdate BETWEEN ? AND ?
                AND trans_type in ('I','D')
                AND upc <> 'RRR'{$this->shrinkageUsers}
            ) as q 
            GROUP BY q.trans_num,q.transaction_type");
        $transR = $dbc->exec_statement($transQ,$dates);
        $transinfo = array();
        while($row = $dbc->fetch_array($transR)){
            if (!isset($transinfo[$row[2]])) {
                $transinfo[$row[2]] = array(0,0.0,0.0,0.0,0.0,0.0);
            }
            $transinfo[$row[2]][0] += 1;
            $transinfo[$row[2]][1] += $row[1];
            $transinfo[$row[2]][3] += $row[3];
        }
        $tSum = 0;
        $tItems = 0;
        $tDollars = 0;
        $this->tProportion = 0.0;
        $totalSales = $reconciliation['Sales'];
        foreach (array_keys($transinfo) as $k) {
            // #'b body Better number_format(x,2)
            $transinfo[$k][2] = round($transinfo[$k][1]/$transinfo[$k][0],2);
            $transinfo[$k][4] = round($transinfo[$k][3]/$transinfo[$k][0],2);
            $transinfo[$k][5] = round(($transinfo[$k][3]/$totalSales)*100,2);
            $transinfo[$k][3] = round($transinfo[$k][3],2);
            $tSum += $transinfo[$k][0];
            $tItems += $transinfo[$k][1];
            $tDollars += $transinfo[$k][3];
            $this->tProportion += $transinfo[$k][5];
        }
        $report = array();
        foreach($transinfo as $title => $info){
            array_unshift($info,$title);
            $report[] = $info;
        }
        $data[] = $report;

        /* Equity */
        $ret = preg_match_all("/[0-9]+/",$FANNIE_EQUITY_DEPARTMENTS,$depts);
        if ($ret != 0){
            /* equity departments exist */
            $depts = array_pop($depts);
            $dlist = "(";
            foreach($depts as $d){
                $dates[] = $d; // add query param
                $dlist .= '?,';
            }
            $dlist = substr($dlist,0,strlen($dlist)-1).")";

            $equityQ = $dbc->prepare_statement("SELECT d.card_no,t.dept_name, sum(total) as total 
                FROM $dlog as d
                LEFT JOIN {$FANNIE_OP_DB}.departments as t ON d.department = t.dept_no
                WHERE d.tdate BETWEEN ? AND ?
                    AND d.department IN $dlist{$this->shrinkageUsers}
                GROUP BY d.card_no, t.dept_name ORDER BY d.card_no, t.dept_name");
            $equityR = $dbc->exec_statement($equityQ,$dates);
            $report = array();
            while($equityW = $dbc->fetch_row($equityR)){
                $record = array($equityW['card_no'],$equityW['dept_name'],
                        sprintf('%.2f',$equityW['total']));
                $report[] = $record;
            }
            $data[] = $report;
        }
        
        return $data;
    }

    function calculate_footers($data)
    {
        switch($this->multi_counter){
        case 1:
            $this->report_headers[0] = 'Tenders';
            /*
             */
            $sumQty = 0;
            $sumAmount = 0.0;
            for ($i=0; $i<count($data); $i++) {
                $sumQty += $data[$i][1];
                $sumAmount += $data[$i][2];
            }
            return array('Total Tenders',
                number_format($sumQty,0),
                '$'.number_format($sumAmount,2)
            );
            break;
        case 2:
            $this->report_headers[0] = 'Sales';
            $sumQty = 0;
            $sumAmount = 0.0;
            for ($i=0; $i<count($data); $i++) {
                $sumQty += $data[$i][1];
                $sumAmount += $data[$i][2];
            }
            return array('Total Sales',
                number_format($sumQty,0),
                '$'.number_format($sumAmount,2)
            );
            break;
        case 3:
            $this->report_headers[0] = 'Discounts';
            $sumQty = 0;
            $sumAmount = 0.0;
            for ($i=0; $i<count($data); $i++) {
                $sumQty += $data[$i][1];
                $sumAmount += $data[$i][2];
            }
            return array('Total Discounts',
                number_format($sumQty,0),
                '$'.number_format($sumAmount,2)
            );
            break;
        case 4:
            $this->report_headers = array('Tax', 'Amount');
            $sumTax = 0.0;
            for ($i=0; $i<count($data)-1; $i++) {
                $sumTax += $data[$i][1];
            }
            return array('Total Sales Tax', '$'.number_format($sumTax,2));
            break;
        case 5:
            $this->report_headers = array('Reconcile Totals', 'Amount');
            $ttl = 0.0;
            foreach ($data as $row) {
                $ttl += $row[1];
            }
            return array('Net', '$'.number_format($ttl,2));
        case 6:
            // #'f Baskets
            $this->report_headers = array('Type','Trans','Items','Avg. Items',
                '$ Amount','Avg. $ Amount','% of $ Amount');
            $trans = 0.0;
            $items = 0.0;
            $amount = 0.0;
            $proportion = 0.0;
            for ($i=0; $i<count($data); $i++) {
                $trans += $data[$i][1];
                $items += $data[$i][2];
                $amount += $data[$i][4];
                // The values in $data[$i][5] make no sense.
                $proportion += $data[$i][5];
            }
            return array('Totals',
                number_format($trans,0),
                number_format($items,2),
                number_format(($items/$trans),2),
                '$'.number_format($amount,2),
                '$'.number_format(($amount/$trans),2),
                number_format($this->tProportion,2).'%',
            );
            break;
        case 7:
            $this->report_headers = array('Mem#','Equity Type', 'Amount');
            $sumSales = 0.0;
            foreach ($data as $row) {
                $sumSales += $row[2];
            }
            return array(null,null,$sumSales);
            break;
        }
        $sumQty = 0.0;
        $sumSales = 0.0;
        foreach($data as $row){
            $sumQty += $row[1];
            $sumSales += $row[2];
        }
        return array(null,$sumQty,$sumSales);
    }

    /* #'C */
    function form_content()
    {
        ob_start();
        ?>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method=get>
        <div class="form-group">
            <label>
                Date
                (Report for a <a href="../GeneralRange/">Range of Dates</a>)
            </label>
            <input type=text id=date name=date 
                class="form-control date-field" required />
        </div>
        <div class="form-group">
            <label>List Sales By</label>
            <select name="sales-by" class="form-control">
                <option>Super Department</option>
                <option>Department</option>
                <option>Sales Code</option>
            </select>
        </div>
        <div class="form-group">
            <label>Excel <input type=checkbox name=excel /></label>
        </div>
        <div class="form-group">
            <label>Sales Reflect Discounts <input type=checkbox name=sales_reflect_discounts 
        <?php if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            echo "checked";
        } ?>
        /></label>
        </div>
        <p>
        <button type=submit name=submit value="Submit"
            class="btn btn-default">Submit</button>
        </p>
        </form>
        <?php
        return ob_get_clean();
    }

    public function helpContent()
    {
        $ret = '';
        $ret .='<p>
            This report lists the four major categories of transaction
            information for a given day: tenders, sales, discounts, and
            taxes.
            </p>
            <p>
            There is a checkbox option <b>Sales Reflect Discounts</b>
            which, when ticked, excludes whole-transaction
            discounts from sales and other pertinent totals.
            </p>
            <p>Terminology:
            <ul>
            <li>
            <b>Tenders</b> are payments given by customers such as cash or credit cards.
            </li>
            <li>
            <b>Sales</b> are items sold to customers.
            </li>
            <li>
            <b>Discounts</b> are percentage discounts associated with an entire
            transaction instead of individual items.
            <br />Item-level discounts are reflected in Sales
            regardless of the Sales Reflect Discount option.
            </li>
            <li>
            <b>Taxes</b> are sales tax collected.
            </li>
            <li>
            <b>Reconciliation note:</b>
            If the Sales Reflect Discount option is in effect
            the amount of Discounts is reported but not included in
            the Reconciliation because it has already been removed from Sales.
            </li>
            </ul>
            </p>
            <p>
            Tenders should equal Sales minus Discounts plus Taxes.
            </p>
            <p>
            Equity and Transaction statistics are provided as generally
            useful information.
            </p>';
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $this->shrinkageUsers = " AND (d.card_no not between 99900 and 99998)";
            $ret .= "<p>This report excludes the In-house accounts using this statement:
                <br /><span style='font-family: courier;'>{$this->shrinkageUsers}</span>
                </p>";
        }
        return $ret;
    }

}

FannieDispatch::conditionalExec(false);

?>
