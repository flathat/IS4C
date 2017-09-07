<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
    Based on example code from Wedge Community Co-op

    This file is part of CORE-POS.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/
/* 28Mar2017 EL WEFC_Toronto version
 * - Cost total
 * - Multiple instances of a upc
 */

include(dirname(__FILE__).'/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once(dirname(__FILE__) . '/../../../classlib2.0/FannieAPI.php');
}

/**
  @class SaReportPage_WEFC_Toronto
*/
class SaReportPage_WEFC_Toronto extends FanniePage {

    public $page_set = 'Plugin :: Shelf Audit';
    public $description = '[Quantity Report] lists the entered quantites on hand - WEFC_Toronto.';
    public $themed = true;
    protected $title = 'ShelfAudit Live Report - WEFC_Toronto';
    protected $header = '';

    private $status = '';
    private $sql_actions = '';
    private $scans = array();
    private $aggregate_upc = False;
    private $view = '';
    private $section_names = array();
    private $inventory_year = '';

    function preprocess(){
        global $FANNIE_PLUGIN_SETTINGS,$FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['ShelfAuditDB']);
        if (!is_object($dbc) || $dbc->connections[$FANNIE_PLUGIN_SETTINGS['ShelfAuditDB']] === False){
            $this->status = 'bad - cannot connect';
            return True;
        }
        if (FormLib::get_form_value('delete') == 'yes'){
            $query=$dbc->prepare_statement('delete from sa_inventory where id=?');
            $result=$dbc->exec_statement($query,array(FormLib::get_form_value('id')));
            if ($result) {
                $this->sql_actions='Deleted record.';
            } else {
                $this->sql_actions='Unable to delete record, please try again. <!-- '.$query.' -->';
            }
        } else if (FormLib::get_form_value('clear') == 'yes'){
            $query=$dbc->prepare_statement('update sa_inventory set clear=1;');
            $result=$dbc->exec_statement($query);
            if ($result) {
                $this->sql_actions='Cleared old scans.';
                header ("Location: {$_SERVER['PHP_SELF']}");
                return False;
            } else {
                $this->sql_actions='Unable to clear old scans, try again. <!-- '.$query.' -->';
            }
        } else if (FormLib::get('change')=='yes') {
        }

        if (FormLib::get_form_value('aggregate_upc','False') == 'True'){
            $this->aggregate_upc = True;
        }
        $this->view = FormLib::get_form_value('view','sect');
        if (FormLib::get_form_value('view') == 'dept'){
            if ($this->aggregate_upc) {
                $order='d.dept_no,s.section,s.upc';
            } else {
                if (FormLib::get_form_value('dept_report_order','') != ''){
                    $order= FormLib::get_form_value('dept_report_order');
                } else {
                    $order='d.dept_no,s.section,s.datetime';
                }
            }
        }
        elseif(FormLib::get_form_value('excel') == 'yes'){
            $order='salesCode, d.dept_no, s.datetime';
        } 
        else {
            if ($this->aggregate_upc) {
                $order='s.section,d.dept_no,s.upc';
            } else {
                if (FormLib::get_form_value('sect_report_order','') != ''){
                    $order= FormLib::get_form_value('sect_report_order');
                } else {
                    $order='s.section,d.dept_no,s.datetime';
                }
            }
        }

        /* Populate section_names
         */
        $query = "SELECT section_number, section_name, section_description
            FROM sa_inventory_sections
            ORDER BY section_number";
        $statement = $dbc->prepare($query);
        $rsect = $dbc->execute($statement, array());
        while ($sect = $dbc->fetchRow($rsect)) {
            $this->section_names[$sect['section_number']] =
                $sect['section_name'];
        }

        /* omitting wedge-specific temp tables Andy 29Mar2013
         * Restore from SaReportPage.php
         */

        $where = '';
        if (FormLib::get_form_value('section_number','') != ''){
            $where .= ' AND s.section = ' .  FormLib::get_form_value('section_number');
        }
        if (FormLib::get_form_value('department_number','') != ''){
            $where .= ' AND d.dept_no = ' .  FormLib::get_form_value('department_number');
        }
        if (FormLib::get_form_value('upc_number','') != ''){
            $where .= ' AND s.upc = ' .  FormLib::get_form_value('upc_number');
        }

        /* 
         * $q_products does not use vendorItems values.
         * $q_vendors prefers products values but falls back to vendorItems values.
         * Not sure this info esp cost from vendorItems is a good idea.
            CASE WHEN p.cost = 0 AND v.cost IS NOT NULL THEN v.cost ELSE p.cost END as cost,
         */

        $q_products = 'SELECT
            s.id,
            s.datetime,
            s.upc,
            s.quantity,
            s.section,
            CASE 
                WHEN p.description IS NULL THEN \'Not in POS\' 
                ELSE p.description END as description,
            CASE 
                WHEN p.brand IS NULL THEN \'\' 
                ELSE p.brand END as brand,
            CASE
                WHEN COALESCE(p.unitofmeasure,\'\') = \'\' THEN p.size
                ELSE CONCAT(p.size,p.unitofmeasure) END as package,
            CASE WHEN d.dept_name IS NULL THEN \'Unknown\' ELSE d.dept_name END as dept_name,
            CASE WHEN d.dept_no IS NULL THEN \'n/a\' ELSE d.dept_no END as dept_no,
            CASE WHEN d.salesCode IS NULL THEN \'n/a\' ELSE d.salesCode END as salesCode,

            COALESCE(p.cost, 0) AS cost,

            p.normal_price as normal_retail,

            CASE WHEN p.discounttype > 0 THEN p.special_price
            ELSE p.normal_price END AS actual_retail,

            CASE WHEN p.discounttype = 2 THEN \'M\'
            ELSE \'\' END AS retailstatus,

            COALESCE(z.vendorName,b.vendorName,\'n/a\') AS vendor,

            COALESCE(c.margin, d.margin, 0) AS margin,

            0 AS is_aggregate

            FROM sa_inventory AS s LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'products AS p
            ON s.upc=p.upc LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'departments AS d
            ON p.department=d.dept_no LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendorItems AS v
            ON s.upc=v.upc AND v.vendorID=1 LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendorItems AS a
            ON p.upc=a.upc AND p.default_vendor_id=a.vendorID LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendors AS b
            ON a.vendorID=b.vendorID LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendorDepartments AS c
            ON a.vendorID=c.vendorID AND a.vendorDept=c.deptID LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendors AS z
            ON p.default_vendor_id=z.vendorID
            WHERE clear!=1' . $where .
            ' ORDER BY '.$order;

        $q_vendors = 'SELECT
            s.id,
            s.datetime,
            s.upc,
            s.quantity,
            s.section,
            CASE 
                WHEN p.description IS NULL AND v.description IS NULL THEN \'Not in POS\' 
                WHEN p.description IS NULL AND v.description IS NOT NULL THEN v.description
                ELSE p.description END as description,
            CASE 
                WHEN p.brand IS NULL AND v.brand IS NULL THEN \'\' 
                WHEN p.brand IS NULL AND v.brand IS NOT NULL THEN v.brand
                ELSE p.brand END as brand,
            CASE
                WHEN COALESCE(p.unitofmeasure,\'\') = \'\' THEN p.size
                ELSE CONCAT(p.size,p.unitofmeasure) END as package,
            CASE WHEN d.dept_name IS NULL THEN \'Unknown\' ELSE d.dept_name END as dept_name,
            CASE WHEN d.dept_no IS NULL THEN \'n/a\' ELSE d.dept_no END as dept_no,
            CASE WHEN d.salesCode IS NULL THEN \'n/a\' ELSE d.salesCode END as salesCode,

            CASE WHEN p.cost = 0 AND v.cost IS NOT NULL THEN v.cost ELSE p.cost END as cost,

            p.normal_price as normal_retail,

            CASE WHEN p.discounttype > 0 THEN p.special_price
            ELSE p.normal_price END AS actual_retail,

            CASE WHEN p.discounttype = 2 THEN \'M\'
            ELSE \'\' END AS retailstatus,

            COALESCE(z.vendorName,b.vendorName,\'n/a\') AS vendor,

            COALESCE(c.margin, d.margin, 0) AS margin,

            0 AS is_aggregate

            FROM sa_inventory AS s LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'products AS p
            ON s.upc=p.upc LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'departments AS d
            ON p.department=d.dept_no LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendorItems AS v
            ON s.upc=v.upc AND v.vendorID=1 LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendorItems AS a
            ON p.upc=a.upc AND p.default_vendor_id=a.vendorID LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendors AS b
            ON a.vendorID=b.vendorID LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendorDepartments AS c
            ON a.vendorID=c.vendorID AND a.vendorDept=c.deptID LEFT JOIN '.
            $FANNIE_OP_DB.$dbc->sep().'vendors AS z
            ON p.default_vendor_id=z.vendorID
            WHERE clear!=1' . $where .
            ' ORDER BY '.$order;

        $s= $dbc->prepare($q_products);
        //$s= $dbc->prepare($q_vendors);
        $r=$dbc->execute($s,array());
        $upcs = array();
        if ($r) {
            $this->status = 'Good - Connected';
            $num_rows=$dbc->numRows($r);
            if ($num_rows>0) {
                $this->scans=array();
                /* Keeps only the first instance of the UPC. Why?
                 * - aggregate quantity in section
                 * - aggregate quantity overall
                 */
                $sct = -1;
                // 's
                while ($row = $dbc->fetchRow($r)) {
                    if (True || !isset($upcs[$row['upc']])) { // test probably obs.
                        if ($sct > -1 && $this->aggregate_upc) {
                            if ($this->view == 'sect') {
                                if ($this->scans[$sct]['section'] == $row['section']
                                    && $this->scans[$sct]['upc'] == $row['upc']
                                ) {
                                    $this->scans[$sct]['quantity'] += $row['quantity'];
                                    $this->scans[$sct]['is_aggregate'] = 1;
                                    continue;
                                }
                            } else {
                                if ($this->scans[$sct]['dept_no'] == $row['dept_no']
                                    && $this->scans[$sct]['upc'] == $row['upc']
                                ) {
                                    $this->scans[$sct]['quantity'] += $row['quantity'];
                                    $this->scans[$sct]['is_aggregate'] = 1;
                                    continue;
                                }
                            }
                        }
                        $sct++;
                        $this->scans[$sct] = $row;
                        $upcs[$row['upc']] = true;
                        if ($sct == 0) {
                            $this->inventory_year = substr($row['datetime'],0,4);
                        }
                    }
                }
            } else {
                $this->status = 'Good - Connected, but no scans to report';
            }
        } else {
            $this->status = 'Bad - IT problem';
        }

        if (!empty($this->scans) && FormLib::get_form_value('excel') == 'yes'){
            header("Content-type: text/csv");
            header("Content-Disposition: attachment; filename=inventory_scans.csv");
            header("Pragma: no-cache");
            header("Expires: 0");
            echo $this->csv_content();
            return False;
        }

        return True;
    }

    function css_content(){
        ob_start();
        ?>
#bdiv {
    width: 900px;
    /*width: 768px; */
    margin: auto;
    text-align: center;
}

body table.shelf-audit {
 font-size: small;
 text-align: center;
 border-collapse: collapse;
 width: 100%;
}

body table.shelf-audit caption {
 font-family: sans-mono, Helvetica, sans, Arial, sans-serif;
 margin-top: 1em;
}

body table.shelf-audit th {
 border-bottom: 2px solid #090909;
}

table.shelf-audit tr:hover {
 background-color:#CFCFCF;
}
caption {
    font-size:1.5em;
    font-weight:bold;
}

.center {
 text-align: center;
}
.right {
 text-align: right;
}
.left {
 text-align: left;
}
.small {
 font-size: smaller;
}
#col_sect {
 width: 40px;
}
#col_dept {
 width: 40px;
}
#col_a {
 width: 120px;
}
#col_b {
 width: 100px;
}
#col_c {
 width: 270px;
}
#col_brand {
 width: 150px;
}
#col_d {
 width: 40px;
}
#col_e {
 width: 60px;
}
#col_f {
 width: 20px;
}
#col_g {
 width: 80px;
}
#col_h {
 width: 48px;
}
#col_i {
 width: 48px;
}
        <?php
        return ob_get_clean();
    }

    function csv_content(){
        $ret = "UPC,Description,Vendor,Account#,Dept#,\"Dept Name\",Qty,Cost,Unit Cost Total,Normal Retail,Status,Normal Retail Total\r\n";
        $totals = array();
        $vendors = array();
        foreach($this->scans as $row) {
            if ($row['cost'] == 0 && $row['margin'] != 0) {
                $row['cost'] = $row['normal_retail'] - ($row['margin'] * $row['normal_retail']);
                $row['retailstatus'] .= '*';
            }
            $ret .= sprintf("%s,\"%s\",\"%s\",%s,%s,%s,%.2f,%.2f,%.2f,%.2f,%s,%.2f,\r\n",
                $row['upc'],$row['description'],$row['vendor'],$row['salesCode'],$row['dept_no'],
                $row['dept_name'],$row['quantity'],$row['cost'], ($row['quantity']*$row['cost']),
                $row['normal_retail'],
                $row['retailstatus'],
                ($row['quantity']*$row['normal_retail'])
            );
            if (!isset($totals[$row['salesCode']]))
                $totals[$row['salesCode']] = array('qty'=>0.0,'ttl'=>0.0,'normalTtl'=>0.0,'costTtl'=>0.0);
            $totals[$row['salesCode']]['qty'] += $row['quantity'];
            $totals[$row['salesCode']]['ttl'] += ($row['quantity']*$row['actual_retail']);
            $totals[$row['salesCode']]['normalTtl'] += ($row['quantity']*$row['normal_retail']);
            $totals[$row['salesCode']]['costTtl'] += ($row['quantity']*$row['cost']);
            if ($row['vendor'] != 'UNFI') {
                $row['vendor'] = 'Non-UNFI';
            }
            if (!isset($vendors[$row['vendor']])) {
                $vendors[$row['vendor']] = array();
            }
            if (!isset($vendors[$row['vendor']][$row['salesCode']])) {
                $vendors[$row['vendor']][$row['salesCode']] = array('qty'=>0.0,'ttl'=>0.0,'normalTtl'=>0.0,'costTtl'=>0.0);
            }
            $vendors[$row['vendor']][$row['salesCode']]['qty'] += $row['quantity'];
            $vendors[$row['vendor']][$row['salesCode']]['ttl'] += ($row['quantity']*$row['actual_retail']);
            $vendors[$row['vendor']][$row['salesCode']]['normalTtl'] += ($row['quantity']*$row['normal_retail']);
            $vendors[$row['vendor']][$row['salesCode']]['costTtl'] += ($row['quantity']*$row['cost']);
        }
        $ret .= ",,,,,,,,\r\n";
        foreach($totals as $code => $info){
            $ret .= sprintf(",,TOTAL,%s,,,%.2f,,%.2f,,,%.2f,\r\n",
                    $code, $info['qty'], $info['costTtl'], $info['normalTtl']);
        }
        $ret .= ",,,,,,,,\r\n";
        foreach($vendors as $vendor => $sales) {
            foreach ($sales as $code => $info) {
                $ret .= sprintf(",,%s,%s,,,%.2f,,%.2f,,,%.2f,\r\n",
                        $vendor,$code, $info['qty'], $info['costTtl'], $info['normalTtl']);
            }
        }
        return $ret;
    }

    function body_content(){
        global $FANNIE_URL,$FANNIE_OP_DB,$FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['ShelfAuditDB']);
        ob_start();
        $section_select = '<select name="section_number">
            <option value="">All Sections</option>';
        $section_links = 'Jump to Section#: |';
        foreach ($this->section_names as $key => $value) {
            $section_select .= sprintf('<option value="%d">%s %s</option>', $key, $key, $value);
            $section_links .= sprintf(' <a href="#sect_%s" title="%s">%s</a> |',
                $key, $value, $key);
        }
        $section_select .= '</select>';
        $dept_order_select = '<select name="dept_report_order">';
        $dept_order_select .= '<option value="">Default: Department, Section</option>';
        $dept_order_select .= '<option value="d.dept_no,s.datetime">Department, Date/time entered</option>';
        $dept_order_select .= '<option value="d.dept_no,s.upc">Department, UPC/PLU</option>';
        $dept_order_select .= '</select>';
        $sect_order_select = '<select name="sect_report_order">';
        $sect_order_select .= '<option value="">Default: Section, Department</option>';
        $sect_order_select .= '<option value="s.section,s.datetime">Section, Date/time entered</option>';
        $sect_order_select .= '<option value="s.section,s.upc">Section, UPC/PLU</option>';
        $sect_order_select .= '</select>';

        $department_select = '<select name="department_number">
            <option value="">All Departments</option>';
        $department_links = 'Jump to Department: |';
        $query = "SELECT dept_no, dept_name
            FROM sa_inventory AS i
            INNER JOIN {$FANNIE_OP_DB}.products AS p ON p.upc = i.upc
            INNER JOIN {$FANNIE_OP_DB}.departments AS d ON d.dept_no = p.department
            ORDER BY dept_no";
        $statement = $dbc->prepare($query);
        $result = $dbc->execute($statement,array());
        //foreach ($this->department_names as $key => $value) {
        $last_dept_no = -1;
        while ($row = $dbc->fetchRow($result)) {
            if ($row['dept_no'] == $last_dept_no) {
                continue;
            }
            $key = $row['dept_no'];
            $key = $row['dept_no'];
            $department_select .= sprintf('<option value="%d">%s %s</option>',
                $row['dept_no'],
                $row['dept_no'],
                $row['dept_name']
            );
            $department_links .= sprintf(' <a href="#dept_%s" title="%s">%s</a> |',
                $row['dept_no'],
                $row['dept_name'],
                $row['dept_no']
            );
            $last_dept_no = $row['dept_no'];
        }
        $department_select .= '</select>';
        ?>
        <div id="bdiv">
            <!-- SaScanningPage link was here -->
            <p class="left">Status: <?php echo($this->status); ?></p>
            <p style="text-align:left;"><?php echo($this->sql_actions); ?></p>
            <div class="left">
            <a name="menu"><h4>Menu:</h4></a>
            <ul>
                <li><a href="#" onclick="window.open('SaScanningPage_WEFC_Toronto.php','scan','width=640, height=400, location=no, menubar=no, status=no, toolbar=no, scrollbars=no, resizable=no');">Scan Utility, in new window</a>
                </li>

                <li><span style="font-weight:bold;">View this report by Floor Section:</span>
            <br /><?php if ($this->view == 'sect') { echo $section_links; } ?>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
            <p style="line-height: 1.5em;">
            Section#: <?php echo $section_select; ?>
            <br />Only UPC#: <input name="upc_number" type="text" size="15" />
            <br />Aggregate UPC counts: <input name="aggregate_upc" type="checkbox" value="True" />
            <br />Sort by: <?php echo $sect_order_select; ?>
            <br /><input type="submit" value="View by Section" />
                <input type="hidden" name="view" value="sect" />
            </p>
            </form>
                </li>

                <li><span style="font-weight:bold;">View this report by POS Department:</span>
            <br /><?php if ($this->view == 'dept') { echo $department_links; } ?>
            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
            <p style="line-height: 1.5em;">
            Department: <?php echo $department_select; ?>
            <!-- Only department#: <input name="department_number" size="5" / -->
            <br />Only UPC#: <input name="upc_number" type="text" size="15" />
            <br />Aggregate UPC counts: <input name="aggregate_upc" type="checkbox" value="True" />
            <br />Sort by: <?php echo $dept_order_select; ?>
            <br /><input type="submit" value="View by Department" />
                <input type="hidden" name="view" value="dept" />
            </p>
            </form>
                </li>

                <li><a href="?excel=yes">Download preliminary scan data as CSV</a>
                </li>
        <?php
        // Risky and rarely wanted. Should have a confirmation popup if used.
        if (False && $this->scans) {
            $clear = '<li><a href="' .
                $_SERVER['PHP_SELF'] . '?clear=yes">Clear existing scans</a></li>';
            echo $clear;
        }
        ?>
            <li><a href="SaFinalReportPage.php?sort=Final&date1=<?php echo $this->inventory_year; ?>-03-31&date2=<?php echo $this->inventory_year; ?>-03-31"
                  target="_final">Final Physical Inventory Report</a>
                </li>

                <li><a href="?menu=yes">Start This Report Over</a>
                </li>
            </ul>
            </div><!-- menu -->
        <?php

        $table = '';

        /* Not used yet. In case column heads are the same for all types
         * of report.
         */
        $column_heads = '
            <thead>
                <tr>
                    <th>Sect.</th>
                    <th>Dept.</th>
                    <th>Date+Time</th>
                    <th>UPC</th>
                    <th>Brand</th>
                    <th>Description</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit Cost</th>
                    <th class="right">Total Cost</th>'
                    /*
                    . '<th>Retail (Normal)</th>'
                    . '<th>Retail (Current)</th>'
                    . '<th>Sale</th>'
                    . '<th>Total Retail</th>'
                     */
                    . '<th class="right">Delete</th>'
                . '</tr>
            </thead>
            ';
        //$view = FormLib::get_form_value('view','sect');
        $counter = ($this->view == 'dept') ? 'd' : 's';
        $counter_total = 0;
        $quantity_total = 0;
        $last_upc = '';
        $last_row = array();
        $pending_row = array();
        foreach($this->scans as $row) {
            
            /* obsolete
            if (False && $this->aggregate_upc) {
                if ($row['upc'] == $last_upc) {
                    // Add quantity to the previous row.
                    $last_row['quantity'] += $row['quantity'];
                    //$last_row['cost'] += $row['cost'];
                    continue;
                } else {
                    // Set aside the new row as pending
                    $pending_row = $row;
                    // Swap the aggregated row in
                    if (!empty($last_row)) {
                        $row = $last_row;
                    // Display the aggregated row
                    // Bring the pending row back
                    }
                }
            }
             */
            $edit_upc = sprintf('<a href="%s%s%s">%s</a>',
                $FANNIE_URL,
                'item/ItemEditorPage.php?searchupc=',
                $row['upc'],
                $row['upc']);

            if (!isset($counter_number)) {
                if ($counter=='d') {
                    $counter_number=$row['dept_no'];
                } else {
                    $counter_number=$row['section'];
                }
                
                $counter_total = ($row['quantity'] * $row['cost']);
                $quantity_total = $row['quantity'];
                //$counter_total=$row['quantity']*$row['normal_retail'];

                if ($counter=='d') {
                    $caption=sprintf('<a name="dept_%s" href="#menu">Department: %s - %s</a>
                        &nbsp; <span style="font-size:0.7em;"><a href="#menu">top-of-page</a></span>',
                        $row['dept_no'],
                        $row['dept_no'],
                        $row['dept_name']);
                }
                else {
                    $caption=sprintf('<a name="sect_%s" href="#menu">Section #%s %s</a>
                        &nbsp; <span style="font-size:0.7em;"><a href="#menu">top-of-page</a></span>',
                        $row['section'],
                        $row['section'],
                        $this->section_names[$counter_number]);
                }

                $table .= '
        <table class="table shelf-audit">
        <caption>'.$caption.'</caption>' .
        $column_heads .
            '<tbody>';
                $table .= '
                <tr>
                    <td id="col_sect" class="center">'.$row['section'].'</td>
                    <td id="col_dept" class="center">'.$row['dept_no'].'</td>
                    <td id="col_a" class="small left">'.$row['datetime'].'</td>
                    <td id="col_b" class="left">' . $edit_upc . '</td>
                    <td id="col_brand" class="left">'.$row['brand'].'</td>
                    <td id="col_c" class="left">'.$row['description'].' '.$row['package'].'</td>
                    <td id="col_d" class="right">'.$row['quantity'].'</td>
                    <td id="col_e" class="right">'.
                        money_format('%.2n', $row['cost']).'</td>
                    <td id="col_h" class="right">'.
                        money_format('%!.2n', ($row['quantity']*$row['cost'])).'</td>'
                    /*
                    . '<td id="col_e" class="right">'.
                        money_format('%.2n', $row['normal_retail']).'</td>'
                    . '<td id="col_f" class="right">'.
                        money_format('%.2n', $row['actual_retail']).'</td>'
                    . '<td id="col_g">'.(($row['retailstatus'])?$row['retailstatus']:'&nbsp;').'</td>'
                    . '<td id="col_h" class="right">'.
                        money_format('%!.2n', ($row['quantity']*$row['normal_retail'])).'</td>'
                     */
                        . '<td id="col_i">' .
                    (($row['is_aggregate'] == 0) ? '<a href="' .
                        $_SERVER['PHP_SELF'] . '?delete=yes&id='.$row['id'].'">'
                        . \COREPOS\Fannie\API\lib\FannieUI::deleteIcon() : 'Multiple')

                        . '</td>'
                . '</tr>';
            } else if ($counter_number!=$row['section'] && $counter_number!=$row['dept_no']) {
                if ($counter=='d') {
                    $counter_number=$row['dept_no'];
                }
                else {
                    $counter_number=$row['section'];
                }
                
                if ($counter=='d') {
                    $caption=sprintf('<a name="dept_%s" href="#menu">Department: %s - %s</a>
                        &nbsp; <span style="font-size:0.7em;"><a href="#menu">top-of-page</a></span>',
                        $row['dept_no'],
                        $row['dept_no'],
                        $row['dept_name']);
                }
                else {
                    $caption=sprintf('<a name="sect_%s" href="#menu">Section #%s %s</a>
                        &nbsp; <span style="font-size:0.7em;"><a href="#menu">top-of-page</a></span>',
                        $row['section'],
                        $row['section'],
                        $this->section_names[$counter_number]);
                }

                $table .= '
            </tbody>
            <tfoot>
                <tr>
                    <td colspan=6>&nbsp;</td>' 
                    . '<td class="right">'.sprintf('%.2f', $quantity_total).'</td>'
                    . '<td colspan=1>&nbsp;</td>'
                    . '<td class="right">'.money_format('$%.2n', $counter_total).'</td>'
                    . '<td colspan=1>&nbsp;</td>'
                . '</tr>
            </tfoot>
        </table>
        <table class="table shelf-audit">
        <caption>'.$caption.'</caption>' .
        $column_heads .
            '<tbody>';
                /*
            $table .= '
                <tr>
                    <td id="col_a" class="small left">'.$row['datetime'].'</td>
                    <td id="col_b" class="left">'.$row['upc'].'</td>
                    <td id="col_c" class="left">'.$row['description'].'</td>
                    <td id="col_d" class="right">'.$row['quantity'].'</td>
                    <td id="col_e" class="right">'.money_format('%.2n', $row['cost']).'</td>
                    <td id="col_h" class="right">'.money_format('%!.2n', ($row['quantity']*$row['cost'])).'</td>
                    <td id="col_e" class="right">'.money_format('%.2n', $row['normal_retail']).'</td>
                    <td id="col_f" class="right">'.money_format('%.2n', $row['actual_retail']).'</td>
                    <td id="col_g">'.(($row['retailstatus'])?$row['retailstatus']:'&nbsp;').'</td>
                    <td id="col_h" class="right">'.money_format('%!.2n', ($row['quantity']*$row['normal_retail'])).'</td>
                    <td id="col_i"><a href="' .
                    $_SERVER['PHP_SELF'] . '?delete=yes&id='.$row['id'].'">'
                        . \COREPOS\Fannie\API\lib\FannieUI::deleteIcon() . '</td>
                </tr>';
                 */
                $table .= '
                <tr>
                    <td id="col_sect" class="center">'.$row['section'].'</td>
                    <td id="col_dept" class="center">'.$row['dept_no'].'</td>
                    <td id="col_a" class="small left">'.$row['datetime'].'</td>
                    <td id="col_b" class="left">' . $edit_upc . '</td>
                    <td id="col_brand" class="left">'.$row['brand'].'</td>
                    <td id="col_c" class="left">'.$row['description'].' '.$row['package'].'</td>
                    <td id="col_d" class="right">'.$row['quantity'].'</td>
                    <td id="col_e" class="right">'.
                        money_format('%.2n', $row['cost']).'</td>
                    <td id="col_h" class="right">'.
                        money_format('%!.2n', ($row['quantity']*$row['cost'])).'</td>'
                    /*
                    . '<td id="col_e" class="right">'.
                        money_format('%.2n', $row['normal_retail']).'</td>'
                    . '<td id="col_f" class="right">'.
                        money_format('%.2n', $row['actual_retail']).'</td>'
                    . '<td id="col_g">'.(($row['retailstatus'])?$row['retailstatus']:'&nbsp;').'</td>'
                    . '<td id="col_h" class="right">'.
                        money_format('%!.2n', ($row['quantity']*$row['normal_retail'])).'</td>'
                     */
                    . '<td id="col_i">' .
                        (($row['is_aggregate'] == 0) ? '<a href="' .
                            $_SERVER['PHP_SELF'] . '?delete=yes&id='.$row['id'].'">'
                            . \COREPOS\Fannie\API\lib\FannieUI::deleteIcon() : 'Multiple')
                        . '</td>'
                . '</tr>';
                
                $counter_total=($row['quantity']*$row['cost']);
                $quantity_total = $row['quantity'];
                //$counter_total=$row['quantity']*$row['normal_retail'];
            } else {
                $counter_total+=$row['quantity']*$row['cost'];
                $quantity_total += $row['quantity'];
                //$counter_total+=$row['quantity']*$row['normal_retail'];
                
                /*
                $table .= '
                <tr>
                    <td id="col_a" class="small left">'.$row['datetime'].'</td>
                    <td id="col_b" class="left">'.$row['upc'].'</td>
                    <td id="col_c" class="left">'.$row['description'].'</td>
                    <td id="col_d" class="right">'.$row['quantity'].'</td>
                    <td id="col_e" class="right">'.money_format('%.2n', $row['cost']).'</td>
                    <td id="col_h" class="right">'.money_format('%!.2n', ($row['quantity']*$row['cost'])).'</td>
                    <td id="col_e" class="right">'.money_format('%.2n', $row['normal_retail']).'</td>
                    <td id="col_f" class="right">'.money_format('%.2n', $row['actual_retail']).'</td>
                    <td id="col_g">'.(($row['retailstatus'])?$row['retailstatus']:'&nbsp;').'</td>
                    <td id="col_h" class="right">'.money_format('%!.2n', ($row['quantity']*$row['normal_retail'])).'</td>
                    <td id="col_i"><a href="' .
                    $_SERVER['PHP_SELF'] . '?delete=yes&id='.$row['id'].'">'
                        . \COREPOS\Fannie\API\lib\FannieUI::deleteIcon() . '</td>
                </tr>';
                 */
                $table .= '
                <tr>
                    <td id="col_sect" class="center">'.$row['section'].'</td>
                    <td id="col_dept" class="center">'.$row['dept_no'].'</td>
                    <td id="col_a" class="small left">'.$row['datetime'].'</td>
                    <td id="col_b" class="left">' . $edit_upc . '</td>
                    <td id="col_brand" class="left">'.$row['brand'].'</td>
                    <td id="col_c" class="left">'.$row['description'].' '.$row['package'].'</td>
                    <td id="col_d" class="right">'.$row['quantity'].'</td>
                    <td id="col_e" class="right">'.
                        money_format('%.2n', $row['cost']).'</td>
                    <td id="col_h" class="right">'.
                        money_format('%!.2n', ($row['quantity']*$row['cost'])).'</td>'
                    /*
                    . '<td id="col_e" class="right">'.
                        money_format('%.2n', $row['normal_retail']).'</td>'
                    . '<td id="col_f" class="right">'.
                        money_format('%.2n', $row['actual_retail']).'</td>'
                    . '<td id="col_g">'.(($row['retailstatus'])?$row['retailstatus']:'&nbsp;').'</td>'
                    . '<td id="col_h" class="right">'.
                        money_format('%!.2n', ($row['quantity']*$row['normal_retail'])).'</td>'
                     */
                    . '<td id="col_i">' .
                        (($row['is_aggregate'] == 0) ? '<a href="' .
                            $_SERVER['PHP_SELF'] . '?delete=yes&id='.$row['id'].'">'
                            . \COREPOS\Fannie\API\lib\FannieUI::deleteIcon() : 'Multiple')
                        . '</td>'
                . '</tr>';
            }

            $last_up = $row['upc'];
            $last_row = $row;
        }
    
        /* money_format() isn't grouping with commas. Because?
         * . '<td class="right">'.money_format('$%.2n', $counter_total).'</td>'
         */
        $table .= '
            </tbody>
            <tfoot>
                <tr>
                    <td colspan=6>&nbsp;</td>' 
                    . '<td class="right">'.sprintf('%.2f', $quantity_total).'</td>'
                    . '<td colspan=1>&nbsp;</td>'
                    . '<td class="right">' . '$' . number_format($counter_total,2).'</td>'
                    . '<td colspan=1>&nbsp;</td>
                </tr>
            </tfoot>
        </table>
        </div>
';
        if (!empty($table))
            print_r($table);
        ?>
        <?php

        return ob_get_clean();
    }

    /**
      User-facing help text explaining how to 
      use a page.
      @return [string] html content
    */
    public function helpContent()
    {
        $ret = '';
        $ret .= '<p>This report shows the current items scanned.
            It is up-to-date as of the last scan at the time it was run
            so it can be used during the inventory/audit to monitor progress.
            </p>';
        $ret .= '<p>Some issues:
            <ul>';
        $ret .= '<li>"Aggregate" means to group into one line two or more scans for the same UPC/PLU.
                It can be useful if, say, counts of items on the shelf and in overstock are entered separately.
                NOT aggregating may expose unintentional multiple scans of the same item.
                <br />Aggregating cannot be used in combination with Sorting by date/time,
                i.e. it will be ignored if date/time sort is chosen.
                </li>';
        $ret .= '<li>Sorting by date/time within either Department or Section
                puts the most recent entries at the end of each Department or Section
                making it easier to see what has just been entered.
                </li>';
        $ret .= '<li>Cost is from the Products List at the moment the report is run.
            If the report is run after Inventory Day (during which costs are
            assumed NOT to be changed) it may not reflect the correct value of
            the item on Inventory Day and should therefore not be used for
            an Inventory Report.
            <br />The Inventory Report uses a snapshot of costs from the Products
            List as of Inventory Day.
            The costs in that snapshot don\'t change as a result of changes to
            vendor price lists but also do not reflect later fixes to the
            Product List
            for costs that should have been in effect on Inventory Day.
                </li>';
        $ret .= '</ul>';
        return $ret;
    }

}

FannieDispatch::conditionalExec(false);

