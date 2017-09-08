<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

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
 * EL: Cost as well as Retail
 *     Title Shrink Report not DDD Report
 *     Ignore trans_subtype
 */

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class ShrinkReport extends FannieReportPage 
{
    public $description = '[Shrink Report] lists items marked as Shrink, or DDD at the registers.';
    public $themed = true;

    protected $title = "Fannie : Shrink Report";
    protected $header = "Shrink Report";
    protected $report_headers = array('Date','UPC','Item','Dept#','Dept Name','Account#', 'Super Dept', 'Qty','Cost $','Retail $','Reason', 'Loss');
    protected $required_fields = array('submitted');

    protected $sort_direction = 1;

    public function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $FANNIE_TRANS_DB = $this->config->get('TRANS_DB');

        $dtrans = $FANNIE_TRANS_DB . $dbc->sep() . 'transarchive';
        $union = true;
        $args = array();
        try {
            $date1 = $this->form->date1;
            $date2 = $this->form->date2;
            $dtrans = DTransactionsModel::selectDTrans($date1, $date2);
            $union = false;
            $args[] = $date1 . ' 00:00:00';
            $args[] = $date2 . ' 23:59:59';
        } catch (Exception $ex) {
            $date1 = '';
            $date2 = '';
        }

        /**
          I'm using {{placeholders}}
          to build the basic query, then replacing those
          pieces depending on date range options
          EL: Ignore if you can, as 2.7 does, else:
                    AND trans_subtype IN ('','0','NA')
        */
        $query = "SELECT
                    YEAR(datetime) AS year,
                    MONTH(datetime) AS month,
                    DAY(datetime) AS day,
                    d.upc,
                    d.description,
                    d.department,
                    e.dept_name,
                    SUM(d.quantity) AS quantity,
                    SUM(d.total) AS total,
                    SUM(d.cost) AS cost,
                    s.description AS shrinkReason,
                    m.super_name,
                    e.salesCode,
                    d.charflag
                  FROM {{table}} AS d
                    LEFT JOIN departments AS e ON d.department=e.dept_no
                    LEFT JOIN ShrinkReasons AS s ON d.numflag=s.shrinkReasonID
                    LEFT JOIN MasterSuperDepts AS m ON d.department=m.dept_ID
                  WHERE trans_status = 'Z'
                    AND trans_type IN ('D', 'I')
                    AND emp_no <> 9999
                    AND register_no <> 99
                    AND upc <> '0'
                    {{date_clause}}
                  GROUP BY
                    YEAR(datetime),
                    MONTH(datetime),
                    DAY(datetime),
                    d.upc,
                    d.description,
                    d.department,
                    e.dept_name,
                    s.description";
        
        $fullQuery = '';
        if (!$union) {
            // user selected date range
            $fullQuery = str_replace('{{table}}', $dtrans, $query);
            $fullQuery = str_replace('{{date_clause}}', 'AND datetime BETWEEN ? AND ?', $fullQuery);
        } else {
            // union of today (dtransaction)
            // plus last quarter (transarchive)
            $today_table = $FANNIE_TRANS_DB . $dbc->sep() . 'dtransactions';
            $today_clause = ' AND ' . $dbc->datediff($dbc->now(), 'datetime') . ' = 0';
            $query1 = str_replace('{{table}}', $today_table, $query);
            $query1 = str_replace('{{date_clause}}', $today_clause, $query1);
            $query2 = str_replace('{{table}}', $dtrans, $query);
            $query2 = str_replace('{{date_clause}}', '', $query2);
            $fullQuery = $query1 . ' UNION ALL ' . $query2;
        }

        $data = array();
        $prep = $dbc->prepare($fullQuery);
        $result = $dbc->execute($prep, $args);
        while ($row = $dbc->fetch_row($result)) {
            $record = array(
                    date('Y-m-d', mktime(0, 0, 0, $row['month'], $row['day'], $row['year'])),
                    $row['upc'],
                    $row['description'],
                    $row['department'],
                    $row['dept_name'],
                    $row['salesCode'],
                    $row['super_name'],
                    sprintf('%.2f', $row['quantity']),
                    sprintf('%.2f', $row['cost']),
                    sprintf('%.2f', $row['total']),
                    empty($row['shrinkReason']) ? 'n/a' : $row['shrinkReason'],
                    $row['charflag'] == 'C' ? 'No' : 'Yes',
            );
            $data[] = $record;
        }

        return $data;
    }

    public function calculate_footers($data)
    {
        if (count($data) == 0) {
            return array();
        }

        $sum_qty = 0.0;
        $sum_cost = 0.0;
        $sum_total = 0.0;
        foreach($data as $row) {
            $sum_qty += $row[7];
            $sum_cost += $row[8];
            $sum_total += $row[9];
        }

        return array('Totals',
            '&nbsp;',
            '&nbsp;',
            '&nbsp;',
            '&nbsp;',
            '&nbsp;',
            '&nbsp;',
            sprintf('%.2f',$sum_qty), sprintf('$ %.2f',$sum_cost), sprintf('$ %.2f', $sum_total),
            '&nbsp;',
            '&nbsp;'
        );
    }
    
    public function form_content()
    {
        return '
        <form action="' . $_SERVER['PHP_SELF'] . '" method="get">
<div class="well">Dates are optional; omit for last quarter</div>
<div class="col-sm-4">
    <div class="form-group">
    <label>Date Start</label>
    <input type=text id=date1 name=date1 class="form-control date-field" />
    </div>
    <div class="form-group">
    <label>Date End</label>
    <input type=text id=date2 name=date2 class="form-control date-field" />
    </div>
    <p>
    <button type=submit name=submitted value=1 class="btn btn-default btn-core">Submit</button>
    <button type=reset name=reset class="btn btn-default btn-reset">Start Over</button>
    </p>
</div>
<div class="col-sm-4">'
    . FormLib::date_range_picker() . '
</div>
</form>
        ';
    }

    public function helpContent()
    {
        return '<p>
            List items marked as shrink for a given date range. In this
            context, shrink is tracking losses.
            </p>';
    }
}

FannieDispatch::conditionalExec();

