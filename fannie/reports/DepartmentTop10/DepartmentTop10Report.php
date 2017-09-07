<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op
    Copyright 2015 River Valley Market, Northampton MA

    This file is part of CORE-POS.

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
/*
 * TODO:
 * [ ] Support buyer=0 as the non-retail or whatever it is superdept
 *      which I don't think I understood when I wrote this.
 */

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class DepartmentTop10Report extends FannieReportPage 
{
    protected $report_cache = 'none';
    protected $title = "Fannie : Department Top 10 Movement";
    protected $header = "Department Top 10 Movement";
    protected $top_unit = "";
    protected $superDeptName = '';
    protected $deptsList = NULL;
    protected $deptPlaceholders = '';
    protected $deptNames = '';
    protected $departmentOrder = '';
    protected $deptOrderBy = '';
    protected $buyer = 0;
    protected $wantMM = 0;

    protected $required_fields = array('date1', 'date2');

    public $description = '[Department Top 10 Movement] lists the top 10, or other number, items by units or $-amount for all superdepartments or the departments in a superdepartment or a group of departments over a date range.';
    public $report_set = 'Movement Reports';
    public $themed = true;

    // #'p
    function preprocess()
    {

        parent::preprocess();

        global $FANNIE_OP_DB;

        if (
            FormLib::get_form_value('date1') !== '' &&
            !FormLib::get_form_value('revised',0)
        ){
            $this->content_function = "report_content";

            $sorthead = FormLib::get_form_value('sorthead', 0);
            if (!$sorthead) {
                $this->sortable = false;
                $this->no_sort_but_style = true;
            }

            $dbc = FannieDB::get($FANNIE_OP_DB);

            $this->deptsList = FormLib::get_form_value('deptsMulti',array());
            $this->departmentOrder = FormLib::get_form_value('departmentOrder','number');
            $buyer = FormLib::get_form_value('buyer', '0');
            /* TODO: create $this->buyer and use it instead of $buyer.
             * Default s/b ''?, not-for-buyer
            $this->buyer = FormLib::get_form_value('buyer', '0');
             */
            $this->deptOrderBy = ($this->departmentOrder == 'number' ? 'dept_no' : 'dept_name');

            $this->wantMM = FormLib::get_form_value('wantMM', '0');

            /* The name of the SuperDept/Buyer for use in the heading.
             */
            if ($buyer && $buyer > 0) {
                $sdn = new SuperDeptNamesModel($dbc);
                $sdn->superID($buyer);
                $sdn->load();
                $this->superDeptName = $sdn->super_name();
            } else {
                $noop = 1;
            }

            $j=count($this->deptsList);
            if ($j>0) {
                $this->deptPlaceholders = '';
                $dSep = '';
                for($i=0;$i<$j;$i++) {
                    $this->deptPlaceholders .= ($dSep . '?');
                    $dSep = ',';
                }
                $deptQ = "SELECT dept_no, dept_name FROM departments
                    WHERE dept_no IN (" .
                    $this->deptPlaceholders .
                    ") ORDER BY {$this->deptOrderBy}";
                $deptS = $dbc->prepare_statement($deptQ);
                $deptR = $dbc->exec_statement($deptS,$this->deptsList);
                $dSep = '';
                while ($row = $dbc->fetch_row($deptR)) {
                    $this->deptNames .= sprintf("%s(%d) %s",
                        $dSep, $row['dept_no'], $row['dept_name']);
                    $dSep = ', ';
                }
            }

            /**
              Check if a non-html format has been requested
               from the links in the initial display, not the form.
            */
            if (FormLib::get_form_value('excel') !== '') {
                $this->report_format = FormLib::get_form_value('excel');
                $this->has_menus(False);
            } elseif (FormLib::get_form_value('navigation',0)) {
                $this->has_menus(true);
            } else {
                $this->has_menus(false);
            }
        }

        return True;

    // preprocess()
    }

    // #'r
    function fetch_report_data()
    {
        global $FANNIE_OP_DB, $FANNIE_ARCHIVE_DB;

        $dbc = FannieDB::get($FANNIE_OP_DB);

        $date1 = FormLib::get_form_value('date1',date('Y-m-d'));
        $date2 = FormLib::get_form_value('date2',date('Y-m-d'));
        $deptStart = FormLib::get_form_value('deptStart','');
        $deptEnd = FormLib::get_form_value('deptEnd','');
        // If the form value doesn't exist treat as though '0' chosen.
        $buyer = FormLib::get_form_value('buyer','0');
        $groupby = FormLib::get_form_value('sort','PLU');
        $store = FormLib::get('store', 0);
        $topN = FormLib::get_form_value('topN', 10);
        $topUnit = FormLib::get_form_value('topUnit','dollars');
        $this->top_unit = $topUnit;
        $reportScope = "each";

        /**
          Build a WHERE condition and other values for use later.
          Superdepartment (buyer) takes precedence over
          department and negative values have special meaning:
          -1 All, -2 All (only) Retail.
        */
        if ($buyer == 0) {
            $filter_condition = 'd.dept_no IN(';
            $filter_condition .= ($this->deptPlaceholders . ')');
            $args = $this->deptsList;
            $orderBy = "d.{$this->deptOrderBy}, {$topUnit} DESC";
        } else if ($buyer !== "" && $buyer > 0) {
            $filter_condition = 's.superID=?';
            $args = array($buyer);
            // Each dept in SuperDept
            // Over all in SuperDept. Do first
            $reportScope = "all";
            $superDeptOrderBy = ($this->departmentOrder == 'number' ? 'superID' : 'super_name');
            $orderBy = "s.{$superDeptOrderBy}, {$topUnit} DESC";
        } else if ($buyer !== "" && $buyer == -1) {
            $filter_condition = "1=1";
            $args = array();
            $superDeptOrderBy = ($this->departmentOrder == 'number' ? 'superID' : 'super_name');
            $orderBy = "s.{$superDeptOrderBy}, {$topUnit} DESC";
            $reportScope = "all";
        } else if ($buyer !== "" && $buyer == -2){
            $filter_condition = "s.superID<>0";
            $args = array();
            $superDeptOrderBy = ($this->departmentOrder == 'number' ? 'superID' : 'super_name');
            $orderBy = "s.{$superDeptOrderBy}, {$topUnit} DESC";
            $reportScope = "all";
        }

        /**
         * Provide more WHERE conditions to filter irrelevant
         * transaction records, as a stop-gap until this is
         * handled more uniformly across the application.
         */
        $filter_transactions = DTrans::isValid() . ' AND ' . DTrans::isNotTesting();
        
        /**
          Select a summary table. For UPC results, per-unique-ring
          summary is needed. For date/dept/weekday results the
          per-department summary is fine (and a smaller table)
        */
        $dlog = DTransactionsModel::selectDlog($date1,$date2);

        /**
            Build an appropriate query depending on the grouping option
            In this report, Top10, it is upc/PLU, there is no choice.
            Order By is different for Dept- and SuperDept-based reports.
        */
        $query = "";
        $superTable = ($buyer !== "" && $buyer > 0) ? 'superdepts' : 'MasterSuperDepts';
        $args[] = $date1.' 00:00:00';
        $args[] = $date2.' 23:59:59';
        $args[] = $store;
        /* #'q
              t.cost as cost,
              p.size as size,
              SUM(t.total) AS dollars,
              -> Is cost of scaled things wrong this way? It is Tcost/Tqty, s/b sum(cost/qty)
                 The cost of the product sold in that transaction. Why is it different?
         */
        // 0=upc, 1brand, 2description, 3size, 4cost, 5price, 6rings, 7qty, 8dollars, 9dept_no, 10dept_name, 11subdept_name
        $query1 = "SELECT t.upc,
              COALESCE(p.brand,x.manufacturer,'') as brand,
              CASE WHEN p.local =1
                  THEN CONCAT(COALESCE(t.description,p.description,''), ' (L)')
              ELSE
                  COALESCE(t.description,p.description,'') END
              as description,
              CONCAT(p.size,
                CASE WHEN COALESCE(p.unitofmeasure,'') != '' THEN '' END,
                    p.unitofmeasure) as size,
              SUM(t.cost) AS cost,
              t.unitPrice as price,
              SUM(CASE WHEN trans_status IN('','0') THEN 1
                WHEN trans_status='V' THEN -1
                ELSE 0 END) as rings," .
              DTrans::sumQuantity('t') . " as qty,
              SUM(t.total) AS dollars,
              d.dept_no,
              d.dept_name,
              sdn.super_name,
              b.subdept_name
              FROM $dlog as t "
                  . DTrans::joinProducts()
                  . DTrans::joinDepartments()
                  . " LEFT JOIN $superTable AS s ON t.department = s.dept_ID
                  LEFT JOIN prodExtra as x on t.upc = x.upc
                  LEFT JOIN subdepts b on p.subdept = b.subdept_no
                  LEFT JOIN superDeptNames sdn on s.superID = sdn.superID
              WHERE $filter_condition
                  AND tdate BETWEEN ? AND ?
                  AND $filter_transactions
                  AND t.trans_type not in ('T')
                  AND " . DTrans::isStoreID($store, 't') .
              " GROUP BY t.upc,
                  COALESCE(p.brand,x.manufacturer,'None'),
                  CASE WHEN p.description IS NULL THEN t.description
                    ELSE p.description END,
                  d.dept_no,
                  d.dept_name,
                  s.superID ";
        $queryOrderBy = " ORDER BY {$orderBy}";
        $query = $query1 . $queryOrderBy;

        /**
          Arrange the results into an array the parent uses to put out the page.
          Date (n/a here) requires a special case to combine
          year, month, and day into a single field.
        */
        $prep = $dbc->prepare_statement($query);
        $result = $dbc->exec_statement($prep,$args);
        $ret = array();
        $currentDept = -999;
        $perDept = 0;
        $deptTopRings = 0;
        $deptTopQuantity = 0;
        $deptTotalSales = 0;
        $deptTopSales = 0;
        if ($buyer >= 0) {
            if ($reportScope == "each") {
                $sectionKey = 'dept_no';
                $sectionKeyName = 'dept_name';
                $sectionFooterLabel = 'of Department Total';
            } else {
                $sectionKey = 'super_name';
                $sectionKeyName = 'super_name';
                $sectionFooterLabel = 'of Super Dept Total';
            }
        } else {
            $sectionKey = 'super_name';
            $sectionKeyName = 'super_name';
            $sectionFooterLabel = 'of Super Dept Total';
        }
        // Headers template. [1] will be assigned for SuperDept/Dept.
        $headers = array('Rank',
            "sectionKeyName",
            'Brand','Description',
            'Size','Cost');
        if ($this->wantMM) {
            $headers = array_merge($headers,array('Margin%','Markup%'));
        }
        $headers = array_merge($headers,array('Price',
            'Rings','Qty','Sales',
            'Dept#',
            'Department',
            'SuperDept',
            'SubDept'
        ));
        // r2R2
        while ($row = $dbc->fetch_array($result)) {
            $record = array();
            if ($row["$sectionKey"] != $currentDept) {
                if ($currentDept != -999) {
                    //  "Top10 is n% of Total" footer
                    $deptFooter = array('','Top Totals','','','','');
                    if ($this->wantMM) {
                        $deptFooter = array_merge($deptFooter, array('',''));
                    }
                    $deptFooter = array_merge($deptFooter,
                        array('',
                        number_format($deptTopRings,0),
                        number_format($deptTopQuantity,2),
                        sprintf('$%s', number_format($deptTopSales,2)),
                        sprintf('%.2f%%', (($deptTopSales/$deptTotalSales)*100)),
                        "$sectionFooterLabel",'',''));
                    $deptFooter['meta'] = FannieReportPage::META_BOLD;
                    $ret[] = $deptFooter;
                    $deptTopRings = 0;
                    $deptTopQuantity = 0;
                    $deptTotalSales = 0;
                    $deptTopSales = 0;
                    $ret[] = array('meta'=>FannieReportPage::META_BLANK);
                    $ret[] = array('meta'=>FannieReportPage::META_REPEAT_HEADERS);
                }
                $currentDept = $row["$sectionKey"];
                $perDept = 0;
                // Add headers for the new Dept.
                $headers[1] = $row["$sectionKeyName"];
                $this->report_headers[] = $headers;
            }
            $perDept++;
            $deptTotalSales += $row['dollars'];
            if ($perDept > $topN) {
                continue;
            }
            $deptTopRings += $row['rings'];
            $deptTopQuantity += $row['qty'];
            $deptTopSales += $row['dollars'];
            // r3R3
            // Format Qty, others are already 2 decimals.
            $record[] = $perDept;
            for($i=0;$i<$dbc->num_fields($result);$i++) {
                if ($i == 4) {
                    $record[] = sprintf('%.2f',$row['cost'] / $row['qty']);
                    /* Markup and Margin only if wanted
                     */
                    if ($this->wantMM) {
                        $pp = 1; // 1=proportion <= 1; 100=percent <= 100
                        $margin = sprintf('%.2f', $row['dollars'] == 0 ? 0 :
                            ($row['dollars'] - $row['cost']) / $row['dollars'] * $pp);
                        /* Markup as $ per item
                        $markup = sprintf('%.2f', $row['qty'] == 0 ? 0 :
                            ($row['dollars'] - $row['cost']) / $row['qty']);
                         */
                        /* Markup as proportion: .nn or percent nn.nn */
                        $markup = sprintf('%.2f', $row['qty'] == 0 ? 0 :
                            (($row['dollars'] - $row['cost']) / $row['cost']) * $pp);
                        $record[] = $margin;
                        $record[] = $markup;
                    }
                } elseif ($i == 5) {
                    //? total-price / rings, ?total/qty
                    $record[] = sprintf('%.2f',$row['price']);
                } elseif ($i == 7) {
                    $record[] = sprintf('%.2f',$row[$i]);
                } else {
                    $record[] .= $row[$i];
                }
            }
            $ret[] = $record;
        }
        //  "Top10 is n% of Total" footer
        //  r4R4
        $deptFooter = array('','Top Totals','','','','');
        if ($this->wantMM) {
            $deptFooter = array_merge($deptFooter, array('',''));
        }
        $deptFooter = array_merge($deptFooter,
            array('',
            number_format($deptTopRings,0),
            number_format($deptTopQuantity,2),
            sprintf('$%s', number_format($deptTopSales,2)),
            sprintf('%.2f%%', (($deptTopSales/$deptTotalSales)*100)),
            "$sectionFooterLabel",'',''));
        $deptFooter['meta'] = FannieReportPage::META_BOLD;
        $ret[] = $deptFooter;

        // #'c When $buyer > 0 do it all again for each dept.
        if ($buyer !== "" && $buyer > 0) {
            $filter_condition = 's.superID=?';
            // Use same $args.
            // Each dept in SuperDept
            $reportScope = "each";
            $orderBy = "d.{$this->deptOrderBy}, {$topUnit} DESC";
            $queryOrderBy = " ORDER BY {$orderBy}";
            $query = $query1 . $queryOrderBy;
            /**
              Copy the results into an array the parent uses to put out the page.
              Date (n/a here) requires a special case to combine
              year, month, and day into a single field.
            */
            $prep = $dbc->prepare_statement($query);
            $result = $dbc->exec_statement($prep,$args);
            //
            // Add to the existing array.
            $currentDept = -999;
            $perDept = 0;
            $deptTopRings = 0;
            $deptTopQuantity = 0;
            $deptTotalSales = 0;
            $deptTopSales = 0;
            $sectionKey = 'dept_no';
            $sectionKeyName = 'dept_name';
            $sectionFooterLabel = 'of Department Total';
            $ret[] = array('meta'=>FannieReportPage::META_BLANK);
            $ret[] = array('meta'=>FannieReportPage::META_REPEAT_HEADERS);
            while ($row = $dbc->fetch_array($result)) {
                $record = array();
                if ($row["$sectionKey"] != $currentDept) {
                    if ($currentDept != -999) {
                        //  "Top10 is n% of Total" footer
                        $deptFooter = array('','Top Totals','','','','');
                        if ($this->wantMM) {
                            $deptFooter = array_merge($deptFooter, array('',''));
                        }
                        $deptFooter = array_merge($deptFooter,
                            array('',
                            number_format($deptTopRings,0),
                            number_format($deptTopQuantity,2),
                            sprintf('$%s', number_format($deptTopSales,2)),
                            sprintf('%.2f%%', (($deptTopSales/$deptTotalSales)*100)),
                            "$sectionFooterLabel",'',''));
                        $deptFooter['meta'] = FannieReportPage::META_BOLD;
                        $ret[] = $deptFooter;
                        /* */
                        $deptTopRings = 0;
                        $deptTopQuantity = 0;
                        $deptTotalSales = 0;
                        $deptTopSales = 0;
                        $ret[] = array('meta'=>FannieReportPage::META_BLANK);
                        $ret[] = array('meta'=>FannieReportPage::META_REPEAT_HEADERS);
                    }
                    $currentDept = $row["$sectionKey"];
                    $perDept = 0;
                    // Add headers for the new Dept.
                    $headers[1] = $row["$sectionKeyName"];
                    $this->report_headers[] = $headers;
                }
                $perDept++;
                $deptTotalSales += $row['dollars'];
                if ($perDept > $topN) {
                    continue;
                }
                $deptTopRings += $row['rings'];
                $deptTopQuantity += $row['qty'];
                $deptTopSales += $row['dollars'];
                // Format Qty, others are already 2 decimals.
                // r6 R6
                $record[] = $perDept;
                for($i=0;$i<$dbc->num_fields($result);$i++) {
                    if ($i == 4) {
                        $record[] = sprintf('%.2f',$row['cost'] / $row['qty']);
                        /* Markup and Margin only if wanted
                         */
                        if ($this->wantMM) {
                            $pp = 1; // 1=proportion <= 1; 100=percent <= 100
                            $margin = sprintf('%.2f', $row['dollars'] == 0 ? 0 :
                                ($row['dollars'] - $row['cost']) / $row['dollars'] * $pp);
                            /* Markup as $ per item
                            $markup = sprintf('%.2f', $row['qty'] == 0 ? 0 :
                                ($row['dollars'] - $row['cost']) / $row['qty']);
                             */
                            /* Markup as proportion: .nn or percent nn.nn */
                            $markup = sprintf('%.2f', $row['qty'] == 0 ? 0 :
                                (($row['dollars'] - $row['cost']) / $row['cost']) * $pp);
                            $record[] = $margin;
                            $record[] = $markup;
                        }
                    } elseif ($i == 5) {
                        //? total-price / rings, ?total/qty
                        $record[] = sprintf('%.2f',$row['price']);
                    } elseif ($i == 7) {
                        $record[] = sprintf('%.2f',$row[$i]);
                    } else {
                        $record[] .= $row[$i];
                    }
                }
                $ret[] = $record;
            }
            //  "Top10 is n% of Total" footer
            $deptFooter = array('','Top Totals','','','','');
            if ($this->wantMM) {
                $deptFooter = array_merge($deptFooter, array('',''));
            }
            $deptFooter = array_merge($deptFooter,
                array('',
                number_format($deptTopRings,0),
                number_format($deptTopQuantity,2),
                sprintf('$%s', number_format($deptTopSales,2)),
                sprintf('%.2f%%', (($deptTopSales/$deptTotalSales)*100)),
                "$sectionFooterLabel",'',''));
            $deptFooter['meta'] = FannieReportPage::META_BOLD;
            $ret[] = $deptFooter;
        }

        // The final package.
        return $ret;

    // fetch_report_data()
    }


    /**
      Sum the quantity and total columns for a footer,
      but also set up headers and sorting.

      The number of columns varies depending on which
      data grouping the user selected. 
    */
    function calculate_footers($data)
    {
        /* This report never has footers.
         * Headers handled elsewhere.
         */
        return array();
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

    function report_description_content()
    {
        $ret = array();

        $d1 = FormLib::get_form_value('date1');
        $d2 = FormLib::get_form_value('date2');
        if ($d2 == '') {
            $d2 = $d1;
        }
        $ret = array();

        $url = $this->config->get('URL');
        $this->add_script($url . 'src/javascript/jquery.js');
        $this->add_script($url . 'src/javascript/jquery-ui.js');
        $this->add_css_file($url . 'src/javascript/jquery-ui.css');

        $dates_form = '<div><form method="post" action="' . $_SERVER['PHP_SELF'] . '" style="float:left;">';
        foreach ($_GET as $key => $value) {
            if ($key != 'date1' && $key != 'date2') {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $dates_form .= sprintf('<input type="hidden" name="%s[]" value="%s" />',
                            $key, $v);
                    }
                } else {
                    $dates_form .= sprintf('<input type="hidden" name="%s" value="%s" />',
                        $key, $value);
                }
            }
        }
        foreach ($_POST as $key => $value) {
            if ($key != 'date1' && $key != 'date2') {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $dates_form .= sprintf('<input type="hidden" name="%s[]" value="%s" />',
                            $key, $v);
                    }
                } else {
                    $dates_form .= sprintf('<input type="hidden" name="%s" value="%s" />',
                        $key, $value);
                }
            }
        }
        $dates_form .= '
            <label>Start Date</label>
            <input class="date-field" type="text" name="date1" value="' .
                FormLib::get('date1') . '" />
            <label>End Date</label>
            <input class="date-field" type="text" name="date2" value="' .
                FormLib::get('date2') . '" />
            <input type="hidden" name="excel" value="" id="excel" />
            <button type="submit" onclick="$(\'#excel\').val(\'\');return true;">' .
            'Change Dates</button>
            </form>';
        //<button type="submit" onclick="$(\'#excel\').val(\'csv\');return true;">
        //Download</button>
        $dates_form .= '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
        $dates_form .= '&nbsp;<button type="submit" onclick="return true;">' .
                'Start Over</button>'.
                '</form>' .
                '</div>';

        $this->add_onload_command("\$('.date-field').datepicker({dateFormat:'yy-mm-dd'});");

        $ret[] = $dates_form;

        $buyer = FormLib::get_form_value('buyer','0');
        $reportFor = '';
        if ($buyer === '0') {
            $reportFor =
                sprintf("Department%s: %s", 
                (count($this->deptsList)>1 ? 's' : ''),
                $this->deptNames);
            $ret[] = "<h3 class='explain'>" . $reportFor .  "</h3>";
        } elseif ($buyer == '-1') {
            $reportFor = "Buyer/SuperDepartment: All";
            $ret[] = "<h3 class='explain'>" . $reportFor .  "</h3>";
        } elseif ($buyer == '-2') {
            $reportFor = "Buyer/SuperDepartment: All Retail";
            $ret[] = "<h3 class='explain'>" . $reportFor .  "</h3>";
        } else {
            $reportFor = "Buyer/SuperDepartment: " .  $this->superDeptName;
            $ret[] = "<h3 class='explain'>" . $reportFor .  "</h3>";
        }

        $topN = FormLib::get_form_value('topN',0);
        $topUnit = FormLib::get_form_value('topUnit','dollars');
        if ($topN) {
            switch($topUnit) {
                case 'dollars':
                    $byWhat = _("dollar value of sales");
                    break;
                case 'qty':
                    $byWhat = _("quantity/weight sold");
                    break;
                case 'rings':
                    $byWhat = _("number of times rung");
                    break;
            }
            $msg = "<p class='explain'>Showing the top $topN items ";
            if ($buyer < 0 ) {
                $msg .= "in each super department ";
            } else if ($buyer > 0 ) {
                $msg .= "for the super department overall, and then for each department, ";
            } else {
                $msg .= "in each department ";
            }
            $msg .= "by {$byWhat}.";
            $msg .= "<br /><span style='font-size:80%;'>'" .
                "(L)' at the end of a product description indicates 'Local'</span>.";
            $msg .= "</p>";
            $ret[] = $msg;
        }

        return $ret;
    }

    /**
      User-facing help text explaining how to 
      use a page.
      @return [string] html content
    */
    public function helpContent()
    {
        $ret = "Selecting a " .
        _("SuperDept") .
        " overrides any Department(s) chosen below.
        <br />To run reports for a specific department(s) leave " .
        _("SuperDept") .
        " as 'Using Departments' and select one or more Departments." .
        "<br /><br />Choosing any regular " . _("SuperDept") . " " .
        "produces a report of Top 10 in that " . _("SuperDept") .
        " followed by a report of the Top 10 in each of its constituent departments." .
        "<br /><br />Choosing " . _("SuperDept") . " 'All' or 'All Retail'
        produces a report of Top 10 in each " . _("SuperDept") . "." .
        "<br /><br />In the Totals footer for each Department,
        the numbers in the 'Rings', 'Quantity' and 'Sales' columns are the
        totals for the Top <em>n</em> items.
        The % to the right of 'Sales' is the proportion of the total Sales
        for the department represented by the Top <em>n</em> items." .
            "<br /><br />The rows in the first department can be re-sorted by
            clicking the column head.
            Re-sorting of 2nd+ departments is a work in progress." .
        "<br /><br />The Department Movement report shows " .
        "similar information with somewhat different options." .
        '';

        return $ret;
    }

    function form_content()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $query = "SELECT dept_no,dept_name, superID
            FROM departments d
            LEFT JOIN superdepts s on s.dept_ID = d.dept_no
            WHERE superID > 0
            ORDER BY superID, dept_name";
        $deptsQ = $dbc->prepare_statement($query);
        $deptsR = $dbc->exec_statement($deptsQ);

        $deptSuperQ = $dbc->prepare_statement("SELECT superID,super_name FROM superDeptNames
                WHERE superID <> 0 
                ORDER BY superID");
        $deptSuperR = $dbc->exec_statement($deptSuperQ);

        $deptSuperList = "";
        while($deptSuperW = $dbc->fetch_array($deptSuperR)) {
            $deptSuperList .=" <option value=$deptSuperW[0]>$deptSuperW[0] $deptSuperW[1]</option>";
        }
        $deptsList = "";
        $deptsMultiList = "";
        while ($deptsW = $dbc->fetch_array($deptsR)) {
            $deptsList .= "<option value=$deptsW[0]>({$deptsW['superID']}) $deptsW[0] $deptsW[1]</option>";
            $deptsMultiList .= "<option value=$deptsW[0]>({$deptsW['superID']}) $deptsW[1] - $deptsW[0]</option>";
        }
        $storesQ = "SELECT * FROM Stores";
        $storesS = $dbc->prepare_statement($storesQ);
        $storesR = $dbc->exec_statement($storesS);
        if ($dbc->num_rows($storesR)>1) {
            $storeWidget = '<div class="form-group" style="width:50%;"> 
        <label for="store">Store:</label>';
            $ret=FormLib::storePicker();
            $storeWidget .= $ret['html'];
            $storeWidget .= '</div>';
        } else {
            $storeWidget = '<input type="hidden" name="store" value="0" />';
        }

/*
 * 2 columns
 * Left:
 *  1 Top how many
 *  2 Top in terms of
 *  3 Dept Order: Name or Number
 *  4 Buyer aka Superdept
 *  5 Buyer/Superdept Note
 *  6 Dept Multi <select>
 *  7 [ ]Sort on col heads. 8 [ ]Disp. Navigation
 *  9 [ ]Show Margin and Markup
 * 10 Report Format <select>
 * 11 Reset   Submit
 * Right:
 *  1 Store, if there is more than one.
 *  2 Date Start
 *  3 Date End
 *  4 Date Picker
*/
?>
<div id=main>    
<form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<!-- Left column -->
<div class="col-sm-6">

    <div class="form-group"> 
        <label for="topN"
             title="The top 'how many' items from each department.">Top
            <i>how many?</i></label>
        <input type="text" size="1" maxlength="3" name="topN" id="topN" value="10" />
    </div>

    <div class="form-group"> 
        <label
            for="topUnit"
            title="'Top' by what measure?"><b>Top <i>in terms of</i></b>
        </label>
            <select id="topUnit" name="topUnit">
            <option value="dollars" selected="">Dollar value</option>
            <option value="qty">Quantity (weight)</option>
            <option value="rings">Rings</option>
            </select>
    </div>

    <div class="form-group"> 
        <label
            for="departmentOrder">Department order
        </label>
                <select id="departmentOrder" name="departmentOrder">
                <option value="number" selected="">Number</option>
                <option value="name">Name</option>
                </select>
    </div>

    <div class="form-group"> 
    <label for="buyer"><?php echo _("SuperDept"); ?></label>
                <select id=buyer name=buyer>
               <option value=0>Using Departments, below</option>
               <option value=-2 >All (only) Retail</option>
               <option value=-1 >All</option>
               <?php echo $deptSuperList; ?>
               </select>
    </div>

    <div class="well"> Selecting
        a <?php echo _("SuperDept");?>, above, overrides
        any Department(s) chosen below.
        <br />To run reports for a specific department(s) leave
        <?php echo _("SuperDept");?> as 'Using Departments' and select
        one or more Departments.
    </div>

    <div class="form-group"> 
        <label for="deptsMulti"
        title="<?php
        $msg="Hold the Ctrl key " .
            "while clicking to select multiple departments " .
            "(orthe apple key if you're using a Mac)" .
            "." .
            " For a range, click the first, hold the Shift key " .
            "and click the last." .
            "";
            echo $msg; ?>">
        (Super) Departments*
        </label>
                 <select name=deptsMulti[] id=deptsMulti multiple size=12 class="form-control"> 
                <?php echo $deptsMultiList ?>
                </select>
    </div>

    <div class="col-sm-3" style="width:50%;">
        <div class="form-group"> 
            <label for="sortable"
                    title="If ticked the report can be re-sorted by clicking column
                    heads." >Sort on col. heads</label>
            <input type=checkbox name=sorthead id=sorthead value=1 checked />
        </div>

        <div class="form-group"> 
            <label for="excel">Format</label>
                    <select id="excel" name="excel">
                        <option value="" selected="">Screen</option>
                        <option value="xls">Excel</option>
                        <option value="csv">CSV</option>
                      </select>
        </div>

        <div class="form-group"> 
            <label for="wantMM"
                    title="If ticked the Margin and Markup of each item will be
                    displayed." >Show Margin and Markup</label>
            <input type=checkbox name=wantMM id=wantMM value=1 
            <?php
            if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
                echo ' checked';
            }
            ?>
            />
        </div>

    </div>

    <div class="col-sm-3" style="width:50%;">
        <div class="form-group"> 
            <label for="navigation"
                    title="If ticked the system navigation menus will be
                    displayed." >Display navigation</label>
            <input type=checkbox name=navigation id=navigation value=1 
    <?php
    if ($this->config->get('WINDOW_DRESSING')) {
        echo ' checked';
    }
    ?>
            />
        </div>
    </div>

<div class="col-sm-6" style="width:100%;">

    <div class="form-group"> 
        <button type=submit name=submit value="Submit"
            class="btn btn-default" style="margin-right:1.0em;">Create Report</button>
        <button type=reset name=reset value="Start Over"
            class="btn btn-default">Start Over</button>
    </div>

</div>


</div><!-- left col -->

<!-- Right column -->
<div class="col-sm-6">

    <?php echo $storeWidget; ?>

    <div class="form-group" style="width:50%;"> 
        <label for="date1">Date range Start</label>
        <input type=text id=date1 name=date1 required
            class="form-control date-field" />
    </div>

    <div class="form-group" style="width:50%;"> 
        <label for="date2">Date range End</label>
        <input type=text id=date2 name=date2 required
            class="form-control date-field" />
    </div>

    <div class="form-group"> 
    <?php echo FormLib::date_range_picker(); ?>
    </div>

    <div class="well">* Hold the Ctrl key
            while clicking to select multiple departments
            (or the apple key if you're using a Mac).
            For a range, click the first, hold the Shift key
            and click the last.
    </div>

</div><!-- right col -->

<input type="hidden" name="sort" value="PLU" />
</form>
</div><!-- form -->
        <?php
    // form_content()
    }

// class
}

FannieDispatch::conditionalExec(false);

?>


