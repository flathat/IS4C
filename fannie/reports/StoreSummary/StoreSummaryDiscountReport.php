<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
    Copyright 2016 West End Food Co-op, Toronto

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

/*
 * 11Nov2016 Handle the incorrect .cost for Voids and Refunds prior to Nov 2016.
 *  5Aug2016 Option for reducing Sales at the department level
 *            by transaction-level discounts.
 *  5Aug2016 Option for suppressing department-level detail.
 * 27Dec2015 Includes the current day, if requested, by merging results from dlog
 *            rather than by UNION. Techniques are different for
 *            the main body of the report, discounts and whole-store taxes.
 * 27Dec2015 Still hard-coded for a two-tax structure.
 * 20Nov2015 I can't figure out how to include superDepts with no trans items.
 *            Simple left join on MasterSuperDepts doesn't do it even if all
 *            WHERE's allow IS NULL.
 * 20Nov2015 Includes recalculation taxes for whole store to reflects discounts.
 */

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class StoreSummaryDiscountReport extends FannieReportPage {

    protected $required_fields = array('date1', 'date2');

    public $description = "[Store Summary Discount Report] shows total sales, costs and taxes
        by Super Department and per Department for a given date range
        in dollars as well as as a percentage of store-wide sales and costs.
       Transaction-level discounts can be reflected in Department-level Sales totals.
       It uses actual item cost if known and estimates 
       cost from price and department margin if not; 
       relies on department margins being accurate.
           Assumes a two-tax regime.
           Can suppress Department level detail.";

    public $report_set = 'Sales Reports';
    public $themed = true;
    protected $sortable = false;
    protected $no_sort_but_style = true;
    protected $show_zero;
    protected $zero_cost_dept;
    protected $zero_margin_cost;
    protected $shrinkageUsers = "";
    protected $sales_reflect_discounts = false;
    protected $super_depts_only = false;

    function preprocess()
    {

        parent::preprocess();

        $this->title = "Fannie : Store Summary Discount Report";
        $this->header = "Store Summary Discount Report";
        if (FormLib::get_form_value('date1') !== ''){
            $this->report_cache = 'none';
            if (FormLib::get_form_value('sortable') !== '') {
                $this->no_sort_but_style = false;
            }
            $this->show_zero =
                (FormLib::get_form_value('show_zero') == '') ? False : True;
            $this->zero_cost_dept =
                (FormLib::get_form_value('zero_cost_dept') == '') ? False : True;
            $this->zero_margin_cost =
                (FormLib::get_form_value('zero_margin_cost') == '') ? False : True;
            $this->sales_reflect_discounts =
                FormLib::get_form_value('sales_reflect_discounts',false);
            $this->super_depts_only =
                FormLib::get_form_value('super_depts_only',false);
            if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
                $this->shrinkageUsers = " AND (t.card_no not between 99900 and 99998)";
            }

            $this->content_function = "report_content";

            /**
              Check if a non-html format has been requested
               from the links in the initial display, not the form.
            */
            if (FormLib::get_form_value('excel') !== '') {
                $this->report_format = FormLib::get_form_value('excel');
                $this->has_menus(False);
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
        // Navigation. Made to look like part of the header.
        $ret[] = "<p class='explain'><a href='" . $_SERVER['PHP_SELF'] . "'>Start over</a>" .
                "" .
                "</p>";
        // Explanaion. A little space around it.
        $note = "<p class='explain' style='margin:0.5em 0em 1.0em 0em;'>";
        if (FormLib::get_form_value('dept',0) == 0){
            $note .= "Using the department# the upc was
                assigned to at time of sale";
        }
        else{
            $note .= "Using the department# the upc is assigned to now";
        }
        $note .= "<br />";
        $note .= "Note for <b>open ring items:</b> The margin in the departments
            table is relied on to calculate cost. 
            If that department margin is zero cost is ";
        $note .= $this->zero_margin_cost ? "same as price." : "zero.";
        $note .= "<br />Note for <b>regular items:</b> Where cost is zero ";
        $note .= $this->zero_cost_dept ? "the departments table is relied on." :
                    "that cost is used.";
        $note .= $this->zero_margin_cost
            ? " If that department margin is zero, cost is same as price."
            : "";
        $note .= "<br />See the <a href='#footer'>footer</a> for notes on section " .
            "and column contents.";
        $note .= "</p>";
        $ret[] = $note;
        return $ret;
    }

    /**
      Extra, non-tabular information appended to the report.
      @return array of strings
    */
    public function report_end_content()
    {
        $ret = array();
        $ret[] = "<a name='footer'> </a>";
        $note = "<ul style='margin:0 0 0 -2.5em; list-style-type:none;'>" .
            "<li><b>Costs</b> is the cost to the co-op of the items sold in the " .
                "Department or Superdepartment." .
            "</li>" .
            "<ul style='margin:0 0 0 -1.0em; list-style-type:none;'>" .
            "<li><b>% Costs</b> is the proportion of the whole store's costs that represents." .
            "</li>" .
            "<li><b>DeptC%</b> is the proportion of the Superdepartment's costs that represents." .
            "</li>" .
            "</ul>" .
            "<li><b>Sales</b> is the price of the items sold in the Department or Superdepartment, ";
        $note .= ($this->sales_reflect_discounts)
            ? "reflecting discounts."
            : "not reflecting discounts.";
        $note .= "<ul style='margin:0 0 0 -1.0em; list-style-type:none;'>" .
            "<li><b>% Sales</b> is the proportion of the whole store's sales that represents." .
            "</li>" .
            "<li><b>DeptS%</b> is the proportion of the Superdepartment's sales that represents." .
            "</li>" .
            "</ul>" .
            "</li>" .
            "</ul>";
        $ret[] = $note;
        if ($this->sales_reflect_discounts) {
            $ret[] ="<p class='explain'>Sales figures are reduced by " .
                "percentages of Whole-transaction Discounts " .
                "as well as per-item discounts and sale prices." .
                "<br />The Member Type/Discounts section shows the totals of " .
                "Whole-transaction Discounts." .
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
        return $ret;
    }

    function fetch_report_data(){
        global $FANNIE_OP_DB, $FANNIE_COOP_ID, $FANNIE_TRANS_DB;

        $d1 = $this->form->date1;
        $d2 = (($this->form->date2 == '') ? $d1 : $this->form->date2);
        $dept = FormLib::get_form_value('dept',0);

        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));

        $dlog = DTransactionsModel::selectDlog($d1,$d2);
        $datestamp = $dbc->identifierEscape('tdate');

        if ($this->sales_reflect_discounts) {
            $salesStatement = '
                SUM(
                CASE WHEN discountable = 1 AND percentDiscount > 0 THEN
                    t.total * (1 - (percentDiscount / 100))
                    ELSE t.total END)
                    AS sales ';
        } else {
            $salesStatement = '
                    SUM(t.total) AS sales ';
        }

        $taxNames = array(0 => '');
        $tQ = "SELECT id, rate, description FROM taxrates WHERE id > 0 ORDER BY id";
        $tS = $dbc->prepare("$tQ");
        $tR = $dbc->execute($tS);
        // Try generating code in this loop for use in SELECT and reporting.
        //  See SalesAndTaxTodayReport.php
        while ( $trow = $dbc->fetchArray($tR) ) {
            $taxNames[$trow['id']] = $trow['description'];
        }

        /* If the range starts before today and includes today
         * make a temporary table for the data (lame).
         * results from each of before-today and today.
         */
        $today = date('Y-m-d');
        $includeToday = false;
        $isTemporary = true;
        // #'c
        if ($d2 >= $today && $d1 != $d2) {
            $includeToday = true;
            $timeStamp = date('Y_m_d_H_i_s');
            $TRANS = $FANNIE_TRANS_DB . $dbc->sep();
            $tempTable = $TRANS . 'temp_StoreSummaryDiscount_' . $timeStamp;
            //$tquery = "CREATE TEMPORARY table $tempTable ENGINE=MEMORY" .
            $tbQ = "CREATE TEMPORARY TABLE $tempTable " .
                "(dname VARCHAR(50),
                    costs DECIMAL(10,2),
                    taxes1 DECIMAL(10,2),
                    taxes2 DECIMAL(10,2),
                    sales DECIMAL(10,2),
                    qty INT(11),
                    sid INT(11),
                    sname VARCHAR(50),
                    dept_no INT(11)
                )";
            $tbS = $dbc->prepare($tbQ);
            $args = array();
            $tbR = $dbc->execute($tbS,$args);
            if ($tbR === false) {
                $dbc->logger("Failed: $tbQ");
            }
            $isTemporary = (stripos($tbQ, 'TEMPORARY') > 0) ? true : false;
            $totalsQforTemp = "SELECT
                    dname,
                    sum(costs) as costs,
                    sum(taxes1) AS taxes1,
                    sum(taxes2) AS taxes2,
                    sum(sales) AS sales,
                    sum(qty) AS qty,
                    sid,
                    sname,
                    dept_no
                FROM $tempTable
                GROUP BY
                    sid, sname, dname, dept_no
                ORDER BY
                    sid, dept_no";
        }

        /**
          Margin column was added to departments but if
          deptMargin is present data may not have been migrated
          i.e. deptMargin is obsolete but may still exist in old systems.
        */
        $margin = 'd.margin';
        $departments_table = $dbc->tableDefinition('departments');
        if ($dbc->tableExists('deptMargin')) {
            $margin = 'm.margin';
        } elseif (!isset($departments_table['margin'])) {
            $margin = '0.00';
        }

        /* Using department settings at the time of sale.
         * I.e. The department# from the transaction.
         *  If that department# no longer exists or is different then the report will be wrong.
         *  This does not use a departments table contemporary with the transactions.
         * [0]Dept_name [1]Cost, [2]HST, [3]GST, [4]Sales, [x]Qty, [x]superID, [x]super_name
         * 10Jun2014 EL Treated margin=0.00 as cost=0 rather than cost=total=100%
         * 10Jun2014 EL Did not use dept margin for trans_type='I' if cost=0
         *               i.e. only used dept margin for open rings.
                     ->Note strange effect on 9900 Pricegun section
        */

        /* Does not accept p.cost = 0
         * t.cost is < 0.00 for Voids and Refunds, so don't ignore it.
         * Is t.cost or p.cost = 0 ever correct?
         * Ultimate default is cost = total.
         *   cost = total is less dangerous (over-optomistic) than cost = 0.
         * The test for the sign of cost not agreeing with that for total is
         *  to deal with a bug in lane-side code where cost was not being
         *  inverted for Voids and Refunds prior to early November, 2016.
        */
        $itemZeroArg = ($this->zero_cost_dept) ? 'AND t.cost != 0.00' : '';
        $marginZeroArg = ($this->zero_margin_cost) ? 't.total' : '0.00';
        $costsCol="sum(CASE
                    WHEN t.trans_type IN ('I','D'){$itemZeroArg} THEN
                        CASE WHEN
                        ((t.cost > 0 AND t.total < 0) || (t.cost < 0 AND t.total > 0))
                        THEN -1 * t.cost
                        ELSE t.cost END
                     WHEN t.trans_type IN ('I','D') AND $margin > 0.00 
                         THEN t.total - (t.total * $margin)
                     WHEN t.trans_type IN ('I','D')
                         THEN $marginZeroArg
                     END) AS costs,";

        // #'t
        if ($dept == 0) {
            $totals = "SELECT
                    d.dept_name dname,
                    {$costsCol}
                    sum(CASE WHEN t.tax = 1 THEN t.total * x.rate ELSE 0 END) AS taxes1,
                    sum(CASE WHEN t.tax = 2 THEN t.total * x.rate ELSE 0 END) AS taxes2,
                    {$salesStatement},
                    sum(t.quantity) AS qty,
                    s.superID AS sid,
                    s.super_name AS sname,
                    d.dept_no
                FROM $dlog AS t
                    LEFT JOIN departments AS d ON d.dept_no=t.department
                    LEFT JOIN MasterSuperDepts AS s ON t.department=s.dept_ID
                    LEFT JOIN taxrates AS x ON t.tax=x.id ";
                if ($margin == 'm.margin') {
                    $totals .= " LEFT JOIN deptMargin AS m ON t.department=m.dept_id ";
                }
                $totals .= "
                WHERE ($datestamp BETWEEN ? AND ?)
                    AND (s.superID > 0 OR s.superID IS NULL) 
                    AND t.trans_type IN ('I','D')
                    AND t.upc != 'DISCOUNT'
                    AND t.trans_subtype not in ('CP','IC'){$this->shrinkageUsers}
                GROUP BY
                    s.superID, s.super_name, d.dept_name, t.department
                ORDER BY
                    s.superID, t.department";

        }
        /* Using current department settings.
         * I.e. The department for the upc from the current products table.
         *  This does not use a departments table contemporary with the transactions.
        */
        elseif ($dept == 1) {
            $totals = "SELECT
                CASE WHEN e.dept_name IS NULL
                        THEN d.dept_name
                        ELSE e.dept_name
                    END AS dname,
                {$costsCol}
                sum(CASE WHEN t.tax = 1 THEN t.total * x.rate ELSE 0 END) AS taxes1,
                sum(CASE WHEN t.tax = 2 THEN t.total * x.rate ELSE 0 END) AS taxes2,
                {$salesStatement},
                sum(t.quantity) AS qty,
                CASE WHEN s.superID IS NULL THEN r.superID ELSE s.superID END AS sid,
                CASE WHEN s.super_name IS NULL THEN r.super_name ELSE s.super_name END AS sname,
                CASE WHEN e.dept_no IS NULL THEN d.dept_no ELSE e.dept_no END AS dept_no
            FROM $dlog AS t
                LEFT JOIN products AS p ON t.upc=p.upc
                LEFT JOIN departments AS d ON d.dept_no=t.department
                LEFT JOIN departments AS e ON p.department=e.dept_no
                LEFT JOIN MasterSuperDepts AS s ON s.dept_ID=p.department
                LEFT JOIN MasterSuperDepts AS r ON r.dept_ID=t.department
                LEFT JOIN taxrates AS x ON t.tax=x.id ";
                if ($margin == 'm.margin') {
                    $totals .= " LEFT JOIN deptMargin AS m ON t.department=m.dept_id ";
                }
            $totals .= "
            WHERE
                ($datestamp BETWEEN ? AND ?)
                AND (s.superID > 0 OR (s.superID IS NULL AND r.superID > 0)
                    OR (s.superID IS NULL AND r.superID IS NULL))
                AND t.trans_type in ('I','D'){$this->shrinkageUsers}
                AND t.upc != 'DISCOUNT'
                AND t.trans_subtype not in ('CP','IC')
            GROUP BY
                CASE WHEN s.superID IS NULL THEN r.superID ELSE s.superID end,
                CASE WHEN s.super_name IS NULL THEN r.super_name ELSE s.super_name END,
                CASE WHEN e.dept_name IS NULL THEN d.dept_name ELSE e.dept_name end,
                CASE WHEN e.dept_no IS NULL THEN d.dept_no ELSE e.dept_no end
            ORDER BY
                CASE WHEN s.superID IS NULL THEN r.superID ELSE s.superID end,
                CASE WHEN e.dept_no IS NULL THEN d.dept_no ELSE e.dept_no end";

        }

        // #'q
        if (!$includeToday) {
            // The whole range is before today.
            $totalsP = $dbc->prepare($totals);
            $totalArgs = array($d1.' 00:00:00', $d2.' 23:59:59');
            $totalsR = $dbc->execute($totalsP, $totalArgs);
        } else {
            // Before today.
            $ttQ = "INSERT INTO $tempTable $totals";
            $ttS = $dbc->prepare($ttQ);
            $totalArgs = array($d1.' 00:00:00', $d2.' 23:59:59');
            $ttR = $dbc->execute($ttS,$totalArgs);
            if ($ttR !== false) {
                $noop = 1;
                //$dbc->logger("OK Before Today Populating: $ttQ");
            } else {
                $dbc->logger("Failed Populating: $ttQ");
            }
            // Today.
            if ($includeToday) {
                $dlogToday = $TRANS . 'dlog';
                $totalsToday = preg_replace("/FROM .* AS t/", "FROM $dlogToday AS t", $totals);
            }
            $ttQ = "INSERT INTO $tempTable $totalsToday";
            $ttS = $dbc->prepare($ttQ);
            $ttR = $dbc->execute($ttS,$totalArgs);
            if ($ttR !== false) {
                $noop = 1;
                //$dbc->logger("OK Today Populating: $ttQ");
            } else {
                $dbc->logger("Failed Today Populating: $ttQ");
            }
            // Get the combined.
            $totalsP = $dbc->prepare($totalsQforTemp);
            $args = array();
            $totalsR = $dbc->execute($totalsP, $args);
            if ($totalsR !== false) {
                $noop = 1;
                //$dbc->logger("OK tempTable getting: $totalsQforTemp");
            } else {
                $dbc->logger("Failed tempTable getting: $totalsQforTemp");
            }
        }

        // The eventual return value.
        $data = array();

        // Array of superDepts in which totals used in the report are accumulated.
        $supers = array();
        $curSuper = 0;
        $grandTotal = 0;
        $this->grandCostsTotal = 0;
        $this->grandSalesTotal = 0;
        // As array grandTax[taxID]
        $this->grandTax1Total = 0;
        $this->grandTax2Total = 0;

        while ($row = $dbc->fetchArray($totalsR)) {
            if ($curSuper != $row['sid']){
                $curSuper = $row['sid'];
            }
            if (!isset($supers[$curSuper])) {
                $supers[$curSuper] = array(
                'name'=>$row['sname'],
                'qty'=>0.0,'costs'=>0.0,'sales'=>0.0,
                'sid'=>$curSuper,
                'taxes1'=>0.0,'taxes2'=>0.0,
                'depts'=>array());
            }
            $supers[$curSuper]['qty'] += $row['qty'];
            $supers[$curSuper]['costs'] += $row['costs'];
            $supers[$curSuper]['sales'] += $row['sales'];
            $supers[$curSuper]['taxes1'] += $row['taxes1'];
            $supers[$curSuper]['taxes2'] += $row['taxes2'];
            $this->grandCostsTotal += $row['costs'];
            $this->grandSalesTotal += $row['sales'];
            $this->grandTax1Total += $row['taxes1'];
            $this->grandTax2Total += $row['taxes2'];
            // GROUP BY produces 1 row per dept. Values are sums.
            $supers[$curSuper]['depts'][] = array('name'=>$row['dname'],
                'qty'=>$row['qty'],
                'costs'=>$row['costs'],
                'sales'=>$row['sales'],
                'taxes1'=>$row['taxes1'],
                'taxes2'=>$row['taxes2']);
        }

        $superCount=1;
        foreach($supers as $s){
            if ($s['sales'] == 0 && !$this->show_zero) {
                $superCount++;
                continue;
            }

            $this->report_headers[] = array("{$s['name']}",'Qty','Costs','% Costs',
                'DeptC%','Sales','% Sales','DeptS %', 'Margin %','GST','HST');

            // add a record (line) for each dept in the superDept
            $superCostsSum = $s['costs'];
            $superSalesSum = $s['sales'];
            foreach($s['depts'] as $d){
                if ($this->super_depts_only) {
                    if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
                        if ($s['sid'] != 4) {
                            continue;
                        }
                    } else {
                        continue;
                    }
                }
                $record = array(
                    $d['name'],
                    sprintf('%.2f',$d['qty']),
                    sprintf('$%.2f',$d['costs'])
                );

                $costPercent = 'n/a';
                if ($this->grandCostsTotal > 0)
                    $costPercent = sprintf('%.2f%%',($d['costs'] / $this->grandCostsTotal) * 100);
                $record[] = $costPercent;

                $costPercent = 'n/a';
                if ($superCostsSum > 0)
                    $costPercent = sprintf('%.2f%%',($d['costs'] / $superCostsSum) * 100);
                $record[] = $costPercent;
    
                $record[] = sprintf('$%.2f',$d['sales']);

                $salePercent = 'n/a';
                if ($this->grandSalesTotal > 0)
                    $salePercent = sprintf('%.2f%%',($d['sales'] / $this->grandSalesTotal) * 100);
                $record[] = $salePercent;

                $salePercent = 'n/a';
                if ($superSalesSum > 0)
                    $salePercent = sprintf('%.2f%%',($d['sales'] / $superSalesSum) * 100);
                $record[] = $salePercent;

                $margin = 'n/a';
                if ($d['sales'] > 0 && $d['costs'] > 0)
                    $margin = sprintf('%.2f%%', (100 * ($d['sales']-$d['costs']) / $d['sales']));
                $record[] = $margin;

                $record[] = sprintf('$%.2f',$d['taxes2']);
                $record[] = sprintf('$%.2f',$d['taxes1']);

                $data[] = $record;
            }

            /* "super record" is a row of totals for the superdept,
             * instead of using calculate_footers().
             */
            $record = array($s['name'],
                    sprintf('%.2f',$s['qty']),
                    sprintf('$%s',number_format($s['costs'],2))
                    );
            $costPercent = 'n/a';
            if ($this->grandCostsTotal > 0)
                $costPercent = sprintf('%.2f%%',($s['costs'] / $this->grandCostsTotal) * 100);
            $record[] = $costPercent;
            $record[] = '';
            $record[] = sprintf('$%s',number_format($s['sales'],2));
            $salePercent = 'n/a';
            if ($this->grandSalesTotal > 0)
                $salePercent = sprintf('%.2f%%',($s['sales'] / $this->grandSalesTotal) * 100);
            $record[] = $salePercent;
            $record[] = '';
            $margin = 'n/a';
            if ($s['sales'] > 0 && $s['costs'] > 0)
                $margin = sprintf('%.2f%%', (100 * ($s['sales']-$s['costs']) / $s['sales']));
            $record[] = $margin;
            $record[] = sprintf('$%.2f',$s['taxes2']);
            $record[] = sprintf('$%.2f',$s['taxes1']);

            $record['meta'] = FannieReportPage::META_BOLD;

            $data[] = $record;

            // Rather than start a new report, insert a blank line between superdepts.
            $data[] = array('meta'=>FannieReportPage::META_BLANK);

            if ($superCount < count($supers)) {
                $data[] = array('meta'=>FannieReportPage::META_REPEAT_HEADERS);
            }
            $superCount++;
        }

        /** Discounts applied at the member type level.
         */
        $report = array();
        $discountData = array();

        /* Headings
        */
        $this->report_headers[] = array(
            'MEMBER TYPE',
            'Qty',
            '',
            '',
            '',
            'Amount',
            '',
            '',
            '',
            '',
            ''
        );

        $data[] = array('meta'=>FannieReportPage::META_REPEAT_HEADERS);
        /* A row for each type of member.
         */
        $dDiscountTotal = 0;
        $dQtyTotal = 0;
        $discQ = "SELECT m.memDesc, 
                    SUM(t.total) AS Discount,
                    count(*) AS ct
                FROM $dlog AS t
                    INNER JOIN {$FANNIE_OP_DB}.memtype m ON t.memType = m.memtype
                WHERE ($datestamp BETWEEN ? AND ?)
                    AND t.upc = 'DISCOUNT'
                    AND t.total <> 0{$this->shrinkageUsers}
                    AND t.trans_subtype not in ('CP','IC')
                GROUP BY m.memDesc
                ORDER BY m.memDesc";
        $discS = $dbc->prepare($discQ);
        $discR = $dbc->execute($discS,$totalArgs);
       $record = array('','','','','','','','','','','');
        while($discW = $dbc->fetchRow($discR)){
            $ddKey= $discW['memDesc'];
            if (array_key_exists($ddKey,$discountData)) {
                $discountData[$ddKey][1] += $discW['ct'];
                    $dQtyTotal += $discW['ct'];
                $discountData[$ddKey][5] += (1*$discW['Discount']);
                    $dDiscountTotal += (1*$discW['Discount']);
            } else {
                $record[0]= $discW['memDesc'];
                $record[1]= $discW['ct'];
                    $dQtyTotal += $discW['ct'];
                $record[5]= (1*$discW['Discount']);
                    $dDiscountTotal += (1*$discW['Discount']);
                $discountData[$ddKey] = $record;
            }
        }
        // #'e If today is included do it again for today.
        if ($includeToday) {
            $dlogToday = $TRANS . 'dlog';
            $discQtoday = preg_replace("/FROM .* AS t/", "FROM $dlogToday AS t", $discQ);
            $discS = $dbc->prepare($discQtoday);
            $discR = $dbc->execute($discS,$totalArgs);
            while($discW = $dbc->fetchRow($discR)){
                $ddKey= $discW['memDesc'];
                if (array_key_exists($ddKey,$discountData)) {
                    $discountData[$ddKey][1] += $discW['ct'];
                        $dQtyTotal += $discW['ct'];
                    $discountData[$ddKey][5] += (1*$discW['Discount']);
                        $dDiscountTotal += (1*$discW['Discount']);
                } else {
                    $record[0]= $discW['memDesc'];
                    $record[1]= $discW['ct'];
                        $dQtyTotal += $discW['ct'];
                    $record[5]= (1*$discW['Discount']);
                        $dDiscountTotal += (1*$discW['Discount']);
                    $discountData[$ddKey] = $record;
                }
            }
        }
        foreach ($discountData as $key => $item) {
            $item[5]= sprintf('$%.2f',$item[5]);
            $data[] = $item;
        }
        // Total Footer
        $record = array(
            "DISCOUNTS",
            number_format($dQtyTotal,0),
            '',
            '',
            '',
            '$'.number_format($dDiscountTotal,2),
            '',
            '',
            '',
            '',
            ''
        );
        $record['meta'] = FannieReportPage::META_BOLD;
        $data[] = $record;
        $data[] = array('meta'=>FannieReportPage::META_BLANK);

        // The discount total is negative.
        if (!$this->sales_reflect_discounts) {
            $this->grandSalesTotal += $dDiscountTotal;
        }

        $this->summary_data[] = $report;

        // End of Discounts

        /** Recalculate Grand Taxes reflecting Discounts
         */
        $this->grandTax1Total = 0;
        $this->grandTax2Total = 0;
        $query = "SELECT
            SUM(t.total * ( 1 - (t.percentDiscount/100)) * r.rate) as taxamt,
            r.description as taxname,
            r.id as taxid
            FROM $dlog AS t
            JOIN {$FANNIE_OP_DB}.taxrates r ON r.id = t.tax
            WHERE ( t.{$datestamp} BETWEEN ? AND ? )
                AND t.trans_subtype not in ('CP','IC')
                AND (t.tax > 0){$this->shrinkageUsers}
            GROUP BY t.tax
            ORDER BY t.tax DESC";
        $statement = $dbc->prepare($query);
        $results = $dbc->execute($statement, $totalArgs);
        while ($res = $dbc->fetchRow($results)) {
            switch ($res['taxid']) {
                case 1:
                    $this->grandTax1Total += $res['taxamt'];
                    break;
                case 2:
                    $this->grandTax2Total += $res['taxamt'];
                    break;
            }
        }
        /* If the date range includes today add the taxes from today.
         */
        if ($includeToday) {
            $dlogToday = $TRANS . 'dlog';
            $queryToday = preg_replace("/FROM .* AS t/", "FROM $dlogToday AS t", $query);
            $statement = $dbc->prepare($queryToday);
            $results = $dbc->execute($statement, $totalArgs);
            while ($res = $dbc->fetchRow($results)) {
                switch ($res['taxid']) {
                    case 1:
                        $this->grandTax1Total += $res['taxamt'];
                        break;
                    case 2:
                        $this->grandTax2Total += $res['taxamt'];
                        break;
                }
            }
        }


        /** The summary of grand totals proportions for the whole store.
         */

        // Headings
        $record = array(
            '',
            '',
            'Costs',
            '',
            '',
            'Sales',
            'Profit',
            '',
            'Margin %',
            isset($taxNames['2']) ? $taxNames['2'] : 'n/a',
            isset($taxNames['1']) ? $taxNames['1'] : 'n/a',
        );
        $record['meta'] = FannieReportPage::META_BOLD;
        $data[] = $record;

        // Grand totals
        $record = array(
            'WHOLE STORE',
            '',
            '$ '.number_format($this->grandCostsTotal,2),
            '',
            '',
            '$ '.number_format($this->grandSalesTotal,2),
            '$ '.number_format(($this->grandSalesTotal - $this->grandCostsTotal),2),
            ''
        );
        $margin = 'n/a';
        if ($this->grandSalesTotal > 0)
            $margin = number_format(((($this->grandSalesTotal - $this->grandCostsTotal) /
                $this->grandSalesTotal) * 100),2).' %';
        $record[] = $margin;
        $record[] = '$ '.number_format($this->grandTax2Total,2);
        $record[] = '$ '.number_format($this->grandTax1Total,2);
        $record['meta'] = FannieReportPage::META_BOLD;
        $data[] = $record;

        $this->grandTTL = $grandTotal;

        // #'d
        /* If tempTable was created TEMPORARY this isn't needed.
         */
        if ($includeToday && ! $isTemporary) {
            $dropQ = "DROP table $tempTable";
            $dropS = $dbc->prepare($dropQ);
            $dropR = $dbc->execute($dropS,array());
            if ($dropR !== false) {
                $noop = 1;
                //$dbc->logger("DROP $tempTable didn't fail.");
            } else {
                $dbc->logger("DROP $tempTable failed.");
            }
        }

        return $data;

    // fetch_report_data()
    }

    public function calculate_footers($data)
    {
        return array();
    // calculate_footers()
    }

    function form_content()
    {
        list($lastMonday, $lastSunday) = \COREPOS\Fannie\API\lib\Dates::lastWeek();
        ob_start();
        ?>
        <form action=<?php echo $_SERVER['PHP_SELF']; ?> method=get>
        <div class="col-sm-5"><!-- left col -->
            <div class="form-group">
                <label>Start Date</label>
                <input type=text id=date1 name=date1 class="form-control date-field" 
                    value="<?php echo $lastMonday; ?>" required />
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type=text id=date2 name=date2 class="form-control date-field" 
                    value="<?php echo $lastSunday; ?>" />
            </div>
            <div class="form-group">
                <select name=dept class="form-control">
                <option value=0>Use department settings at time of sale</option>
                <option value=1>Use current department settings</option>
                </select>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="sortable" />
                    Sortable column heads
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="show_zero" />
                    Show SuperDepts with net $0 sales
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type=checkbox name=sales_reflect_discounts 
            <?php if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
                echo "checked";
            } ?>
            />
                    Sales Reflect Discounts
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="super_depts_only" />
                    Show only SuperDepts
                </label>
            </div>
            <p>
                <button type="submit" class="btn btn-default">Submit</button>
            </p>
        </div><!-- left col -->

        <div class="col-sm-5"><!-- right col -->
            <?php echo FormLib::date_range_picker(); ?>

            <div class="form-group">
                <label>
                <input type="checkbox" name="zero_cost_dept" id="zero_cost_dept" CHECKED />
                For non-open-ring items with cost of zero calculate cost using the
                department margin.
                <label>
            </div>

            <div class="form-group">
                <label>
                <input type="checkbox" name="zero_margin_cost" id="zero_margin_cost" CHECKED />
                If department margin is zero set cost same as price.
                <br />(If un-ticked cost is zero if margin is zero.)
                <label>
            </div>

        </div><!-- right col -->
        </form>
        <?php

        return ob_get_clean();
    // form_content()
    }

    public function helpContent()
    {
       $ret = '';
       $ret .= '<p>Features:
           <ul>
           <li> Shows total sales, costs and taxes by Super Department and
           per Department for a given date range in dollars as well as as a
           percentage of store-wide sales and costs.
          </li>
          <li>Option to reflect transaction-level discounts in Department-level Sales totals.
          Examples of this type of discount are Senior Discounts and Member Appreciation Discounts.
          </li>
          <li>It uses actual item cost if known and estimates cost from price
          and department margin if not; relies on department margins being accurate.
          </li>
          <li>Option to suppress Department level detail, that is, display only Super Department-level totals.
          </li>
          <li>Assumes a two-tax regime.
          </li>
          </ul>
           </p>';
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $this->shrinkageUsers = " AND (d.card_no not between 99900 and 99998)";
            $ret .= "<p>This report excludes the In-house accounts using this statement:
                <br /><span style='font-family: courier;'>{$this->shrinkageUsers}</span>
                </p>";
        }
       return $ret;
    }


// StoreSummaryDiscountReport
}

FannieDispatch::conditionalExec();

