<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op
    Copyright 2017 West End Food Co-op, Toronto

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

if (!class_exists('FannieAPI')) {
    include_once(dirname(__FILE__) . '/../../../classlib2.0/FannieAPI.php');
}

class SaFinalReportPage extends FannieReportPage 
{
    protected $report_cache = 'none';
    protected $title = "Fannie : Final Physical Inventory Reports";
    protected $header = "Final Physical Inventory Reports";

    protected $required_fields = array('date1');

    public $description = '[Physical Inventory] lists total costs for
        inventoried items by department or in detail.';
    public $report_set = 'Other Reports';
    public $themed = true;

    protected $new_tablesorter = true;

    /**
      Lots of options on this report.
    */
    function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $OP = $this->config->get('OP_DB') . $dbc->sep();
        $PS = $this->config->get('PLUGIN_SETTINGS');
        $SADB = $PS['ShelfAuditDB'];
        $dbc->selectDB($SADB);

        $date1 = $this->form->date1;
        //$date2 = $this->form->date2;
        $level = FormLib::get_form_value('level','summary');
        //$store = FormLib::get('store', 0);

        /**
          Build an appropriate query depending on the grouping option
        */
        $query = "";
        $args = array();
        $args[] = $date1.' 00:00:00';
        //$args[] = $date2.' 23:59:59';
        //$args[] = $store;
        $args = array();
        switch($level) {
            case 'summary':
                $query = "SELECT
                    CASE WHEN p.upc IS NULL THEN 0
                      WHEN p.p_department IS NULL THEN 1
                      ELSE p.p_department END AS 'department',
                    CASE WHEN p.upc IS NULL THEN 'NEW TO INVENTORY'
                      WHEN p.p_department IS NULL THEN 'NOT IN INVENTORY PRODUCTS'
                      ELSE d.dept_name END AS 'dept_name',
                    ROUND(SUM(i.quantity),2) AS qty,
                    ROUND(SUM(i.quantity * COALESCE(p.cost,0)),2) AS Sales
                    FROM sa_inventory AS i
                    LEFT JOIN sa_inventory_products AS p ON p.upc = i.upc
                    LEFT JOIN {$OP}departments d
                      ON d.dept_no = p.p_department
                    WHERE i.clear = 0
                    GROUP BY department
                    ORDER BY department";
                break;
            case 'detail':
                $query = "SELECT
                    i.id AS ID,
                    i.upc AS Code,
                    CASE WHEN p.upc IS NULL THEN 0 
                      WHEN p.p_department IS NULL THEN 1
                        ELSE p.p_department END AS 'department',
                        CASE WHEN p.upc IS NULL THEN 'NEW TO INVENTORY'
                          WHEN p.p_department IS NULL THEN 'NOT IN INVENTORY PRODUCTS'
                            ELSE d.dept_name END AS 'dept_name',
                        p_brand AS Brand,
                        p_description AS Description,
                        ROUND(i.quantity,2) AS 'Count',
                        ROUND(COALESCE(p.cost,0),2) AS 'Cost',
                        ROUND(i.quantity * COALESCE(p.cost,0),2) AS 'Ext. Cost'
                        FROM sa_inventory AS i
                        LEFT JOIN sa_inventory_products AS p ON p.upc = i.upc
                        LEFT JOIN {$OP}departments d
                          ON d.dept_no = p.p_department
                        WHERE i.clear = 0
                        ORDER BY i.id";

                break;
        }

        /**
        Copy the results into an array.
        */
        $prep = $dbc->prepare($query);
        $result = $dbc->execute($prep,$args);
        $ret = array();
        while ($row = $dbc->fetch_array($result)) {
            $record = array();
            for($i=0;$i<$dbc->num_fields($result);$i++) {
                // Cost and Weight to 2 decimals
                if (preg_match('/^\d+\.\d+$/', $row[$i])) {
                    $row[$i] = sprintf('%.2f', $row[$i]);
                }
                $record[] .= $row[$i];
            }
            $ret[] = $record;
        }

        return $ret;
    }
    
    /**
      Sum the quantity and total columns for a footer,
      but also set up headers and sorting.

      The number of columns varies depending on which
      data grouping the user selected. 
    */
    function calculate_footers($data)
    {
        // no data; don't bother
        if (empty($data)) {
            return array();
        }

        /**
          Use the width of the first record to determine
          how the data is grouped
        */
        switch(count($data[0])) {
            case 9:

                $this->report_headers = array('ID','Code','Dept#','Department',
                    'Brand', 'Description', 'Count/ Weight', 'Item Cost', 'Total Cost');
                $this->sort_column = 0;
                $this->sort_direction = 0;

                $sumQty = 0.0;
                $sumCosts = 0.0;
                foreach($data as $row) {
                    $sumQty += $row[6]; // meaningless
                    $sumCosts += $row[8];
                }
                return array('Total',null,null,null,null,null,null,null,
                    '$'.number_format($sumCosts,2));

                break;
            case 4:
                $footers = array();
                    $this->report_headers = array('Dept#','Department','Count/ Weight','$Cost');
                    $this->sort_column = 0;
                    $this->sort_direction = 0;

                    $sumQty = 0.0;
                    $sumCosts = 0.0;
                    foreach($data as $row) {
                        $sumQty += $row[2]; // meaningless
                        $sumCosts += $row[3];
                    }
                    return array('Total',null,null,'$'.number_format($sumCosts,2));
                    /*
                    $footers[] = array('Total',null,null,'$'.number_format($sumCosts,2));
                    return $footers;
                     */

                break;
        }
    }

    /**
      Extra, non-tabular information appended to
      reports
      @return array of strings
    public function report_end_content()
    {
        $ret = array();
        $ret[] = "A end1";
        $ret[] = "B end2";
        return $ret;
    }
    */

    /* Override defaultDescriptionContent()
     *  since some not appropriate for this report.
     *
     * Standard lines to include above report data
     * @param $datefields [array] names of one or two date fields
     *   in the GET/POST data. The fields "date", "date1", and
     *   "date2" are detected automatically.
     * @return array of description lines
    */
    protected function defaultDescriptionContent($rowcount, $datefields=array())
    {
        $ret = array();
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $ret[] = "West End Food Co-op";
        }
        $datefields[] = 'date1';
        $dt1 = false;
        $dt2 = false;
        if (count($datefields) == 1) {
            $dt1 = strtotime(FormLib::get($datefields[0])); 
        } elseif (count($datefields) == 2) {
            $dt1 = strtotime(FormLib::get($datefields[0])); 
            $dt2 = strtotime(FormLib::get($datefields[1])); 
        } elseif (FormLib::get('date1','') !== '') {
            $dt1 = strtotime(FormLib::get('date1'));
        } elseif (FormLib::get('date1') !== '' && FormLib::get('date2') !== '') {
            $dt1 = strtotime(FormLib::get('date1'));
            $dt2 = strtotime(FormLib::get('date2'));
        }
        if ($dt1 && $dt2) {
            $ret[] = _('From') . ' ' 
                . date('l, F j, Y', $dt1) 
                . ' ' . _('to') . ' ' 
                . date('l, F j, Y', $dt2);
        } elseif ($dt1 && !$dt2) {
            $ret[] = _('For the Inventory Count taken') . ' ' . date('l, F j, Y', $dt1);
        }
        $ret[] = _('Report generated') . ' ' . date('l, F j, Y g:i A');

        return $ret;
    }

    /* Lines of description above the body of the report
     * following the standard description (defaultDescriptionContent()).
     */
    function report_description_content()
    {
        $ret = array();
        if (False) {
            $ret[] = "Summed by ".FormLib::get_form_value('sort','');
            $buyer = FormLib::get_form_value('buyer','');
            if ($buyer === '0') {
                $ret[] = "Department ".FormLib::get_form_value('deptStart','').
                    ' to '.FormLib::get_form_value('deptEnd','');
            }
        }
        if (False && $this->config->get('COOP_ID') == 'WEFC_Toronto') {
            // Where is the data coming from?
            $date1 = $this->form->date1;
            $date2 = $this->form->date2;
            $dlog = DTransactionsModel::selectDlog($date1,$date2);
            $ret[] = "<p>";
            $ret[] = "dlog for $date1 to $date2 : $dlog";
            $ret[] = "</p>";
        }

        return $ret;
    }

    function form_content()
    {
        ob_start();
?>
    <form method = "get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="form-horizontal">
<div class="row">
    <div class="col-sm-4">
        <!-- ?php echo FormLib::standardDepartmentFields('buyer', 'departments', 'deptStart', 'deptEnd'); ? -->
        <div class="form-group">
            <label class="col-sm-4 control-label">Count Date</label>
            <div class="col-sm-8">
                <input type=text id=date1 name=date1 class="form-control date-field" required />
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-4 control-label">Level of Detail</label>
            <div class="col-sm-8">
                <select name="level" class="form-control">
                    <option value="summary">Summary</option>
                    <option value="detail">Detail</option>
                </select> 
            </div>
        </div>
        <div class="form-group">
            <!-- label class="control-label col-sm-4">Save to Excel
                <input type=checkbox name=excel id=excel value=xls>
            </label -->
            <!-- label class="col-sm-4 control-label">Store</label>
            <div class="col-sm-4">
                <?php $ret=FormLib::storePicker();echo $ret['html']; ?>
            </div -->
        </div>
    </div>
    <div class="col-sm-8">
    </div>
</div>
    <p>
        <button type=submit name=submit value="Submit" class="btn btn-default btn-core">Submit</button>
        <button type=reset name=reset class="btn btn-default btn-reset"
            onclick="$('#super-id').val('').trigger('change');">Start Over</button>
    </p>
</form>
<?php

        return ob_get_clean();
    }

    public function helpContent()
    {
        $ret = '';
        $ret .= '<p>The report of the Inventory Count to submit to the auditor.
            <ul>
            <li>Uses the counts from the Inventory Count
            and a snapshot of cost, department, brand and description that
            is separate from the current Product List and contemporary
            with the Count.
                </li>
                <li>Two formats:
                <ul>
                    <li>Summary - Subtotals by Department
                    </li>
                    <li>Detail - Each item in the Inventory Scan,
                    initially in the order of the scan and not aggregated by item.
                    </li>
                </ul>
            </ul>
            </p>';
        $ret .= '<p>After running this, to prepare an Excel or PDF version,
            do something like:
            <ul>
                <li>Export to Excel
                </li>
                <li>Open the export file in Excel, Google Sheets, Libre Office Calc
                or similar spreadsheet program.
                </li>
                <li>Tweak the formatting, e.g. column widths
                </li>
                <li>Perhaps Save as PDF, if possible.
                </li>
            </ul>
            </p>
                ';
        return $ret;
    }

}

FannieDispatch::conditionalExec();

