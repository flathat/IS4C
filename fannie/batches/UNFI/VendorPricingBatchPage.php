<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op

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
 * 12Sep2017 EL There is a lot of commented code from a a git merge resolution
 * and attempt to keep some upstream features while mostly keeping the WEFC
 * changes.
 * If the code survives test on pos probably remove the cruft and push to origin.
 */

use \COREPOS\Fannie\API\item\Margin;
use \COREPOS\Fannie\API\item\PriceRounder;

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class VendorPricingBatchPage extends FannieRESTfulPage
{
    protected $title = "Fannie - Create Price Change Batch";
    protected $header = "Create Price Change Batch";

    public $description = '[Vendor Price Change] creates a price change batch for a given
    vendor and edits it based on catalog cost information.';
    public $themed = true;

    protected $auth_classes = array('batches');
    protected $must_authenticate = true;

    private $mode = 'start';

    public function css_content()
    {
        return '
        tr.green td.sub {
            background:#ccffcc;
        }
        tr.red td.sub {
            background:#F7BABA;
        }
        tr.white td.sub {
            background:#ffffff;
        }
        th.thead, td.thead {
            background: #fff4d6;
        }
        tr.yellow td.sub {
            background:#ffff96;
        }
        tr.selection td.sub {
            background:#add8e6;
        }
        td.srp {
            text-decoration: underline;
        }
        ';
    }

    public function get_id_view()
    {
        $this->addScript($this->config->get('URL') . 'src/javascript/jquery.floatThead.min.js');
        $this->addScript('pricing-batch.js');
        $dbc = $this->connection;
        $dbc->selectDB($this->config->OP_DB);

        $superID = FormLib::get('super', -1);
        $queueID = FormLib::get('queueID');
        $vendorID = $this->id;
        $filter = FormLib::get_form_value('filter') == 'Yes' ? True : False;

        /* lookup vendor and superdept names to build a batch name */
        $sname = "All";
        if ($superID >= 0) {
            $smodel = new SuperDeptNamesModel($dbc);
            $smodel->superID($superID);
            $smodel->load();
            $sname = $smodel->super_name();
        }
        $vendor = new VendorsModel($dbc);
        $vendor->vendorID($vendorID);
        $vendor->load();

        $batchName = $sname." ".$vendor->vendorName()." PC ".date('m/d/y');

        /* find a price change batch type */
        $types = new BatchTypeModel($dbc);
        $types->discType(0);
        $bType = 0;
        foreach ($types->find() as $obj) {
            $bType = $obj->batchTypeID();
            break;
        }

        /* get the ID of the current batch. Create it if needed. */
        $bidQ = $dbc->prepare("
            SELECT batchID
            FROM batches
            WHERE batchName=?
                AND batchType=?
                AND discounttype=0
            ORDER BY batchID DESC");
        $bidR = $dbc->execute($bidQ,array($batchName,$bType));
        $batchID = 0;
        if ($dbc->numRows($bidR) == 0) {
            $b = new BatchesModel($dbc);
            $b->batchName($batchName);
            $b->startDate('1900-01-01');
            $b->endDate('1900-01-01');
            $b->batchType($bType);
            $b->discountType(0);
            $b->priority(0);
            $batchID = $b->save();
            if ($this->config->get('STORE_MODE') === 'HQ') {
                StoreBatchMapModel::initBatch($batchID);
            }
        } else {
            $bidW = $dbc->fetchRow($bidR);
            $batchID = $bidW['batchID'];
        }

        /* Strategy: Keep all of HEAD until the end of table formatting.
         * Does it work that way?
         */
// <<<<<<< HEAD
        $ret = '';
        $vendorPattern = '<span style="font-size:1.4em;"><b>Vendor:</b> %s </span>' .
            '<b>Discount:</b> %0.2f%% <b>Shipping:</b> %0.2f%%';
        $ret .= sprintf($vendorPattern,
            $vendor->vendorName(),
            $vendor->discountRate(),
            $vendor->shippingMarkup());
        $ret .= '<br />';
        $ret .= sprintf('<b>Batch:</b> 
                    <a href="%sbatches/newbatch/BatchManagementTool.php?startAt=%d">%s</a>',
                    $this->config->URL,
                    $batchID,
                    $batchName);
        $ret .= sprintf("<input type=hidden id=vendorID value=%d />
            <input type=hidden id=batchID value=%d />
            <input type=hidden id=queueID value=%d />
            <input type=hidden id=superID value=%d />",
            $vendorID,$batchID,$queueID,$superID);

        $batchUPCs = array();
        $batchList = new BatchListModel($dbc);
        $batchList->batchID($batchID);
        foreach ($batchList->find() as $obj) {
            $batchUPCs[$obj->upc()] = true;
        }

// <<<<<<< HEAD
        /* From here to the end of displaying the table is different for WEFC_Toronto
         */
        /* upstream uses p.cost instead of v.cost. Significant?
         * Which is more likely to be accurate?
         * Stick with my v.cost for now.
         */
        $costSQL = Margin::adjustedCostSQL('v.cost', 'b.discountRate', 'b.shippingMarkup');
/* =======
        $costSQL = Margin::adjustedCostSQL('p.cost', 'b.discountRate', 'b.shippingMarkup');
        >>>>>>> upstream/version-2.7
 */
        $marginSQL = Margin::toMarginSQL($costSQL, 'p.normal_price');
        $p_def = $dbc->tableDefinition('products');
        /* 
         * Store-department-for-vendor
         * NO:vendor-subcat is highest priority
         */
        $marginCase = '
            CASE
                WHEN g.margin IS NOT NULL AND g.margin <> 0 THEN g.margin
                WHEN s.margin IS NOT NULL AND s.margin <> 0 THEN s.margin
                ELSE d.margin
            END';
        /* Also works. Maybe slower.
        $marginCase = 'COALESCE(
            CASE WHEN g.margin IS NOT NULL AND g.margin <> 0 THEN g.margin ELSE NULL END,
            CASE WHEN s.margin IS NOT NULL AND s.margin <> 0 THEN s.margin ELSE NULL END,
            d.margin)';
         */
        $marginSourceCase = '
            CASE 
                WHEN g.margin IS NOT NULL AND g.margin <> 0 THEN 1
                WHEN s.margin IS NOT NULL AND s.margin <> 0 THEN 2
                ELSE 3
            END';

        /* Delete this when priority is absolutely settled.
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $marginCase = '
                CASE 
                    WHEN g.margin IS NOT NULL AND g.margin <> 0 THEN g.margin
                    WHEN s.margin IS NOT NULL AND s.margin <> 0 THEN s.margin
                    ELSE d.margin
                END';
            $marginSourceCase = '
                CASE 
                    WHEN g.margin IS NOT NULL AND g.margin <> 0 THEN 1
                    WHEN s.margin IS NOT NULL AND s.margin <> 0 THEN 2
                    ELSE 3
                END';
        } else {
            // store-dept-for-vendor is highest priority
            $marginCase = '
                CASE 
                    WHEN g.margin IS NOT NULL AND g.margin <> 0 THEN g.margin
                    WHEN s.margin IS NOT NULL AND s.margin <> 0 THEN s.margin
                    ELSE d.margin
                END';
            $marginSourceCase = '
                CASE 
                    WHEN g.margin IS NOT NULL AND g.margin <> 0 THEN 1
                    WHEN s.margin IS NOT NULL AND s.margin <> 0 THEN 2
                    ELSE 3
                END';
        }
         */

        $srpSQL = Margin::toPriceSQL($costSQL, $marginCase);
        $packageCase = "
            CASE
            WHEN COALESCE(p.unitofmeasure, '') != ''
            THEN CONCAT(p.size,' ',p.unitofmeasure)
            ELSE p.size
            END";

        /* This is commented out upstream.
        //  Scan both stores to find a list of items that are inUse.
        $itemsInUse = array();
        $query = $dbc->prepare("SELECT upc FROM products WHERE inUse = 1");
        $result = $dbc->execute($query);
        while ($row = $dbc->fetchRow($result)) {
            $itemsInUse[$row['upc']] = 1;
        }
        */

        /* $aliasP is new for 2.7
         */
        $aliasP = $dbc->prepare("
            SELECT v.srp,
                v.vendorDept,
                a.multiplier
            FROM VendorAliases AS a
                INNER JOIN vendorItems AS v ON a.sku=v.sku AND a.vendorID=v.vendorID
            WHERE a.upc=?");

        /* $query:
         * HEAD has:
         * - package and marginsource
         * - INNER JOIN to vendorItem
         * HEAD to try:
         * - LEFT JOIN to vendorItems. Seems OK.
         * - LEFT JOIN to vendorAliases b/c some later code needs it. Seems OK.
         * - two booleans alias and likecoded. Seems OK.
         * upstream has:
         * - two booleans alias and likecoded
         * - LEFT JOIN to vendorItem
         * - LEFT JOIN to vendorAliases
         * upstream doesn't have:
         * - brand
         * - package
         */
        $query_head = "SELECT p.upc,
            p.description,
            p.cost,
            b.shippingMarkup,
            b.discountRate,
            p.normal_price,
            " . Margin::toMarginSQL($costSQL, 'p.normal_price') . " AS current_margin,
            " . Margin::toMarginSQL($costSQL, 'v.srp') . " AS desired_margin,
            " . $costSQL . " AS adjusted_cost,
            v.srp,
            " . $srpSQL . " AS rawSRP,
            v.vendorDept,
            x.variable_pricing,
            " . $marginCase . " AS margin,
            p.brand,
            " . $packageCase . " AS package,
            p.department,
            " . $marginSourceCase . " AS marginSource,
            CASE WHEN a.sku IS NULL THEN 0 ELSE 1 END as alias,
            CASE WHEN l.upc IS NULL THEN 0 ELSE 1 END AS likecoded
            FROM products AS p 
                LEFT JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id=v.vendorID
                LEFT JOIN VendorAliases AS a ON p.upc=a.upc AND p.default_vendor_id=a.vendorID
                INNER JOIN vendors as b ON v.vendorID=b.vendorID
                LEFT JOIN departments AS d ON p.department=d.dept_no
                LEFT JOIN vendorDepartments AS s ON v.vendorDept=s.deptID AND v.vendorID=s.vendorID
                LEFT JOIN VendorSpecificMargins AS g ON p.department=g.deptID AND v.vendorID=g.vendorID
                LEFT JOIN prodExtra AS x ON p.upc=x.upc
                LEFT JOIN upcLike AS l ON v.upc=l.upc ";

        $query_up = "SELECT p.upc,
            p.description,
            p.cost,
            b.shippingMarkup,
            b.discountRate,
            p.normal_price,
            " . Margin::toMarginSQL($costSQL, 'p.normal_price') . " AS current_margin,
            " . Margin::toMarginSQL($costSQL, 'v.srp') . " AS desired_margin,
            " . $costSQL . " AS adjusted_cost,
            v.srp,
            " . $srpSQL . " AS rawSRP,
            v.vendorDept,
            x.variable_pricing,
            " . $marginCase . " AS margin,
            CASE WHEN a.sku IS NULL THEN 0 ELSE 1 END as alias,
            CASE WHEN l.upc IS NULL THEN 0 ELSE 1 END AS likecoded
            FROM products AS p
                LEFT JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id=v.vendorID
                LEFT JOIN VendorAliases AS a ON p.upc=a.upc AND p.default_vendor_id=a.vendorID
                INNER JOIN vendors as b ON v.vendorID=b.vendorID
                LEFT JOIN departments AS d ON p.department=d.dept_no
                LEFT JOIN vendorDepartments AS s ON v.vendorDept=s.deptID AND v.vendorID=s.vendorID
                LEFT JOIN VendorSpecificMargins AS g ON p.department=g.deptID AND v.vendorID=g.vendorID
                LEFT JOIN prodExtra AS x ON p.upc=x.upc
                LEFT JOIN upcLike AS l ON v.upc=l.upc ";

        $useHeadQuery = True;
        $query = ($useHeadQuery) ? $query_head : $query_up;
        // There are additions to $query below.

        /* CONFLICTED
        $query = "SELECT p.upc,
            p.description,
            p.cost,
            b.shippingMarkup,
            b.discountRate,
            p.normal_price,
            " . Margin::toMarginSQL($costSQL, 'p.normal_price') . " AS current_margin,
            " . Margin::toMarginSQL($costSQL, 'v.srp') . " AS desired_margin,
            " . $costSQL . " AS adjusted_cost,
            v.srp,
            " . $srpSQL . " AS rawSRP,
            v.vendorDept,
            x.variable_pricing,
            " . $marginCase . " AS margin,
<<<<<<< HEAD
            p.brand,
            " . $packageCase . " AS package,
            p.department,
            " . $marginSourceCase . " AS marginSource
            FROM products AS p 
                INNER JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id=v.vendorID
=======
            CASE WHEN a.sku IS NULL THEN 0 ELSE 1 END as alias,
            CASE WHEN l.upc IS NULL THEN 0 ELSE 1 END AS likecoded
            FROM products AS p
                LEFT JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id=v.vendorID
                LEFT JOIN VendorAliases AS a ON p.upc=a.upc AND p.default_vendor_id=a.vendorID
>>>>>>> upstream/version-2.7
                INNER JOIN vendors as b ON v.vendorID=b.vendorID
                LEFT JOIN departments AS d ON p.department=d.dept_no
                LEFT JOIN vendorDepartments AS s ON v.vendorDept=s.deptID AND v.vendorID=s.vendorID
                LEFT JOIN VendorSpecificMargins AS g ON p.department=g.deptID AND v.vendorID=g.vendorID
                LEFT JOIN prodExtra AS x ON p.upc=x.upc
                LEFT JOIN upcLike AS l ON v.upc=l.upc ";
        end of CONFLICTED */

        $args = array($vendorID);

/* upstream tests a different $superID value
 * and JOINS on a different table.
 * That may be "better" if it doesn't break something else.
 * Keep HEAD for the moment.
 * Might be a good idea to try/compare the upstream technique.
<<<<<<< HEAD
        if ($superID != 99){ //}
            $query .= " LEFT JOIN superdepts AS m
=======
        if ($superID != -1){ //}
            $query .= " LEFT JOIN MasterSuperDepts AS m
>>>>>>> upstream/version-2.7
                ON p.department=m.dept_ID ";
            //{
        }
*/
        if ($superID != 99){
            $query .= " LEFT JOIN superdepts AS m
                ON p.department=m.dept_ID ";
        }
        $query .= "WHERE v.cost > 0
                    AND v.vendorID=?";
        if ($superID == -2) {
            $query .= " AND m.superID<>0 ";
        } elseif ($superID != -1) {
            $query .= " AND m.superID=? ";
            $args[] = $superID;
        }
        if ($filter === false) {
            $query .= " AND p.normal_price <> COALESCE(v.srp,0.00) ";
        }

        $query .= ' AND p.upc IN (SELECT upc FROM products WHERE inUse = 1) ';
        $query .= ' GROUP BY p.upc ';

        $query .= " ORDER BY p.upc";
        if (isset($p_def['price_rule_id'])) {
            $query = str_replace('x.variable_pricing', 'p.price_rule_id AS variable_pricing', $query);
        }
/*
$arg_list = print_r($args,true);
$dbc->logger("q: $query \n $arg_list");
*/

        $prep = $dbc->prepare($query);
        $result = $dbc->execute($prep,$args);
        /* WEFC table layout
            <<<<<<< HEAD
            Use floating head. Needs:
            - id=mytable
            - thead, tbody tags
            - maybe class thead
        $ret .= "<table class=\"table table-bordered small\">";
        $ret .= "<tr><td colspan=7>&nbsp;</td><th colspan=2>Current</th>
         */

        $ret .= "<table class=\"table table-bordered small\" id=\"mytable\">";
        $ret .= "<thead><tr><td colspan=7 class=\"thead\">&nbsp;</td><th colspan=2 class=\"thead\">Current</th>
            <th colspan=5 class=\"thead\">Vendor</th>
            <th colspan=2 class=\"thead\">&nbsp;</th></tr>";
        $ret .= "<tr>
            <th class=\"thead\">UPC</th>
            <th class=\"thead\">Brand</th>
            <th class=\"thead\">Our Description</th>
            <th class=\"thead\">Package</th>
            <th class=\"thead\">Dept</th>
            <th class=\"thead\">Base Cost</th>
            <th class=\"thead\">Adj. Cost</th>
            <th class=\"thead\">Price</th><th class=\"thead\">Margin</th>
            <th class=\"thead\">SRP</th>
            <th class=\"thead\">SRP Margin</th>
            <th class=\"thead\">Raw Price</th>
            <th class=\"thead\">Raw Margin</th>
            <th class=\"thead\">Sub Cat.</th>
            <th class=\"thead\">Var.</th><th class=\"thead\">Batch</th></tr></thead></tbody>";

        $backgroundCounts=array(
            "white" => 0,
            "red" => 0,
            "green" => 0,
            "yellow" => 0,
            "selection" => 0
        );

/* 2.7 table layout.
=======
        $ret .= "<table class=\"table table-bordered small\" id=\"mytable\">";
        $ret .= "<thead><tr><td colspan=6 class=\"thead\">&nbsp;</td><th colspan=2  class=\"thead\">Current</th>
            <th colspan=3  class=\"thead\">Vendor</th><td colspan=3 class=\"thead\"></td></tr>";
        $ret .= "<tr><th class=\"thead\">UPC</th><th class=\"thead\">Our Description</th>
            <th class=\"thead\">Base Cost</th>
            <th class=\"thead\">Shipping</th>
            <th class=\"thead\">Discount%</th>
            <th class=\"thead\">Adj. Cost</th>
            <th class=\"thead\">Price</th><th class=\"thead\">Margin</th><th class=\"thead\">Raw</th>
            <th class=\"thead\">SRP</th>
            <th class=\"thead\">Margin</th><th class=\"thead\">Cat</th><th class=\"thead\">Var</th>
            <th class=\"thead\">Batch</th></tr></thead><tbody>";
        >>>>>>> upstream/version-2.7
*/

        /* $vendorModel is new for 2.7
         * */
        $vendorModel = new VendorItemsModel($dbc);
        while ($row = $dbc->fetch_row($result)) {
            /* Getting $multiplevendors is new in 2.7
             * See how it works to try to keep it.
             */
            $vendorModel->reset();
            $vendorModel->upc($row['upc']);
            $vendorModel->vendorID($vendorID);
            $vendorModel->load();
            $numRows = $vendorModel->find();
            $multipleVendors = '';
            if (count($numRows) > 1) {
                $multipleVendors = '<span class="glyphicon glyphicon-exclamation-sign"
                    title="Multiple SKUs For This Product">
                    </span> ';
            }
            /* alias is new in 2.7, don't know what to do
             * Does it ever apply at WEFC? I doubt it.
             * Is used to change the value of srp.
             */
            if ($row['alias']) {
                $alias = $dbc->getRow($aliasP, array($row['upc']));
                $row['vendorDept'] = $alias['vendorDept'];
                $row['srp'] = $alias['srp'] * $alias['multiplier'];
            }
            $background = "white";
            if (isset($batchUPCs[$row['upc']]) && !$row['likecoded']) {
                $background = 'selection';
            } elseif ($row['variable_pricing'] == 0 && $row['normal_price'] < 10.00) {
                $background = (
                    ($row['normal_price']+0.10 < $row['rawSRP'])
                    && ($row['srp']-.14 > $row['normal_price'])
                ) ?'red':'green';
                if ($row['normal_price']-.10 > $row['rawSRP']) {
                    $background = (
                        ($row['normal_price']-.10 > $row['rawSRP'])
                        && ($row['normal_price']-.14 > $row['srp'])
                        && ($row['rawSRP'] < $row['srp']+.10)
                    )?'yellow':'green';
                }
            } elseif ($row['variable_pricing'] == 0 && $row['normal_price'] >= 10.00) {
                $background = ($row['normal_price'] < $row['rawSRP']
                    && $row['srp'] > $row['normal_price']) ?'red':'green';
                if ($row['normal_price']-0.49 > $row['rawSRP']) {
                    $background = ($row['normal_price']-0.49 > $row['rawSRP']
                        && ($row['normal_price'] > $row['srp'])
                        && ($row['rawSRP'] < $row['srp']+.10) )?'yellow':'green';
                }
            }
            if (isset($batchUPCs[$row['upc']])) {
                $icon = '<span class="glyphicon glyphicon-minus-sign"
                    title="Remove from batch">
                    </span>';
            } else {
                $icon = '<span class="glyphicon glyphicon-plus-sign"
                    title="Add to batch">
                    </span>';
            }
            /* WEFC change, no cols for shipping, discount
                <td class=\"sub shipping\">%.2f%%</td>
                <td class=\"sub discount\">%.2f%%</td>
                $row['shippingMarkup']*100,
                $row['discountRate']*100,
             */
/* CONFLICTED
                Un-CONFLICTED begins:
            $ret .= sprintf("<tr id=row%s class=%s>
                <td class=\"sub\"><a href=\"%sitem/ItemEditorPage.php?searchupc=%s\">%s</a></td>
                <td class=\"sub\">%s</td> // brand
<<<<<<< HEAD 6 cols
                <td class=\"sub\">%s</td>
                <td class=\"sub\">%s</td>
                <td class=\"sub\">%d</td>
                <td class=\"sub cost\">%.2f</td> // change from %.2f to %.3f
                <td class=\"sub adj-cost\">%.2f</td> // change from %.2f to %.3f
                <td class=\"sub price\"><b>%.2f</b></td>
======= 5 cols
                Seems to be missing description.
                <td class=\"sub cost\">%.3f</td>
                <td class=\"sub shipping\">%.2f%%</td>
                <td class=\"sub discount\">%.2f%%</td>
                <td class=\"sub adj-cost\">%.3f</td>
                <td class=\"sub price\">%.2f</td>
                >>>>>>> upstream/version-2.7
                CONFLICTED
                Un-CONFLICTED begins:
                <td class=\"sub cmargin\">%.2f%%</td>
                <td onclick=\"reprice('%s');\" class=\"sub srp\">%.2f</td>
 */
            $ret .= sprintf("<tr id=row%s class=%s>
                <td class=\"sub\"><a href=\"%sitem/ItemEditorPage.php?searchupc=%s\">%s</a></td>
                <td class=\"sub\">%s</td>
                <td class=\"sub\">%s</td>
                <td class=\"sub\">%s</td>
                <td class=\"sub\">%d</td>
                <td class=\"sub cost\">%.2f</td>
                <td class=\"sub adj-cost\">%.2f</td>
                <td class=\"sub price\"><b>%.2f</b></td>
                <td class=\"sub cmargin\">%.2f%%</td>
                <td onclick=\"reprice('%s');\" class=\"sub srp\">%.2f</td>
                <td class=\"sub dmargin\">%.2f%%</td>
                <td class=\"sub raw-srp\">%.2f</td>
                <td class=\"sub rmargin\">%d: %.2f%%</td>
                <td class=\"sub\">%d</td>
                <td><input class=varp type=checkbox onclick=\"toggleV('%s');\" %s /></td>
                <td class=white>
                    <a class=\"add-button %s\" href=\"\"
                        onclick=\"addToBatch('%s'); return false;\">
                        <span class=\"glyphicon glyphicon-plus-sign\"
                            title=\"Add item to batch\"></span>
                    </a>
                    <a class=\"remove-button %s\" href=\"\"
                        onclick=\"removeFromBatch('%s'); return false;\">
                        <span class=\"glyphicon glyphicon-minus-sign\"
                            title=\"Remove item from batch\"></span>
                    </a>
                </td>
                </tr>",
                $row['upc'],
                $background,
                $this->config->URL, $row['upc'], $row['upc'],
                $row['brand'],
                /* try to keep the new $multipleVendors. So far so good.
                 */
                $row['description'] . ' ' . $multipleVendors,
                $row['package'],
                $row['department'],
                $row['cost'],
                $row['adjusted_cost'],
                $row['normal_price'],
                100*$row['current_margin'],
                $row['upc'],
                $row['srp'],
                100*$row['desired_margin'],
                $row['rawSRP'],
                $row['marginSource'],
                100*$row['margin'],
                $row['vendorDept'],
                $row['upc'],
                ($row['variable_pricing']>=1?'checked':''),
                (isset($batchUPCs[$row['upc']])?'collapse':''), $row['upc'],
                (!isset($batchUPCs[$row['upc']])?'collapse':''), $row['upc']
            );

            $backgroundCounts[$background]++;
        }
        $ret .= "</table>";

        $ret .= "<h4>Counts</h4>";
        $ret .= "<table class=\"table table-bordered small\" style=\"width:20%;\">
            <tr><th>Type</th><th class=\"text-right\">Number</th>
            <th class=\"text-right\">PerCent</th></tr>";
        $btotal = 0;
        foreach ($backgroundCounts as $key => $count) {
            $btotal += $count;
        }
        foreach ($backgroundCounts as $type => $count) {
            $ret .= sprintf('<tr class="%s"><td class="sub">%s</td>
                <td class="sub text-right">%d</td>
                <td class="sub text-right">%0.2f%%</td></tr>',
                $type, $type, $count, (($count/$btotal)*100));
        }
//<<<<<<< HEAD Totals row is new by WEFC.
        $ret .= sprintf('<tr><td>%s</td><td class="text-right">%d</td>
            <td class="text-right">%0.2f%%</td></tr>',
                "Total", $btotal, (($btotal/$btotal)*100));
        $ret .= "</tbody></table>";
        $ret .= "</p>";
/* =======
        $ret .= "</tbody></table>";
        >>>>>>> upstream/version-2.7
 */

        // #'c id
        return $ret;
    // get_id_view()
    }

    public function get_view()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->OP_DB);

        /* upstream 2.7 uses MasterSuperDepts instead of superDeptNames.
         * Keep upstream. cruft.
         */
/* <<<<<<< HEAD
        $prep = $dbc->prepare("SELECT superID,super_name
            FROM superDeptNames
=======
        $prep = $dbc->prepare("
            SELECT superID,
                super_name
            FROM MasterSuperDepts
            >>>>>>> upstream/version-2.7
 */
        $prep = $dbc->prepare("
            SELECT DISTINCT superID,
                super_name
            FROM MasterSuperDepts
            WHERE superID > 0
            ORDER BY super_name");
        $res = $dbc->execute($prep);
        $opts = "<option value=\"-1\" selected>All</option>";
        $opts .= "<option value=\"-2\" selected>All Retail</option>";
        while ($row = $dbc->fetch_row($res)) {
            $opts .= "<option value={$row['superID']}>{$row['super_name']}</option>";
        }

        $vmodel = new VendorsModel($dbc);
        $vopts = "";
        foreach ($vmodel->find('vendorName') as $obj) {
            $vopts .= sprintf('<option value="%d">%s</option>',
                $obj->vendorID(), $obj->vendorName());
        }

        $queues = new ShelfTagQueuesModel($dbc);
        $qopts = $queues->toOptions();

        ob_start();
        ?>
        <form action=VendorPricingBatchPage.php method="get">
        <label>Select a Vendor</label>
        <select name="id" class="form-control">
        <?php echo $vopts; ?>
        </select>
        <label>and a Super Department</label>
        <select name=super class="form-control">
        <?php echo $opts; ?>
        </select>
        <label>Show all items</label>
        <select name=filter class="form-control">
        <option>No</option>
        <option>Yes</option>
        </select>
        <label>Shelf Tag Queue</label>
        <select name="queueID" class="form-control">
        <?php echo $qopts; ?>
        </select>
        <br />
        <p>
        <button type=submit class="btn btn-default">Continue</button>
        </p>
        </form>
        <?php

        return ob_get_clean();

    // get_view()
    }

    public function javascript_content()
    {
        ob_start();
        ?>
        var $table = $('#mytable');
        $table.floatThead();
        <?php
        return ob_get_clean();
    }

    public function helpContent()
    {

        $ret = '<p>Review products from the vendor with current vendor cost,
            retail price, and margin information. The tool creates a price
            change batch in the background. It will add items to this batch
            and automatically create shelf tags.</p>
            <p>The default <em>Show all items</em> setting, No, omits items
            whose current retail price is identical to the margin-based
            suggested retail price.</p>
            ';

        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $text_replace = True;
            if ($text_replace) {
                $ret = '';
            }
            $ret .= '<h4>Overview</h4>';
            $ret .= '<p>This is usually used just after a new price list
                from the vendor has been loaded into the vendor catalogue.
                </p>
                ';
            $ret .= '<p>Use it to change store prices by means of a
                Price Change Batch.
                </p>
                ';
            /*
            $ret .= '<p>Review products from the vendor with current vendor cost,
                retail price, and margin information and optionally add them
                to a batch.
                </p>
                ';
             */
            $ret .= '<p>Upon submitting the form the tool creates an empty Price Change Batch.
                ';
            $ret .= '<br />- You then add items to this batch by selecting them from the list.
                <br />- When the Batch is completely populated go to Batch Management to
                schedule it and make shelf tags.
                </p>
                ';
            $ret .= '<h4>The Form</h4>';
            $ret .= ' <p><em>Show all items</em> The default setting, "No", omits items
                whose current retail price is identical to the margin-based
                suggested retail price.
                </p>
                ';
            $ret .= '<h4>The List</h4>';
            $ret .= ' <p>This is a list of Products that have information in the Vendor Catalogue
                that may have been updated recently.
                </p>
                ';
            $ret .= '<p>The name of the (initially empty) batch is noted in the upper left.
                Click it to go to Batch Management.
                ';
            $ret .= '<p>Columns
                <ul>
                    <li>UPC - linked to the Item Editor
                    </li>
                    <li>Our Description - from the Products List, not the Vendor Catalogue
                    </li>
                    <li>Dept - Store Department
                    </li>
                    <li>Base Cost - Case-cost divided by units-per-case
                    </li>
                    <li>Adj. Cost - Base Cost less Discount plus Shipping
                    <br />Discount and Shipping are the same percentage of cost
                    for all items from the Vendor.
                    </li>
                    <li>Current Group
                        <ul>
                        <li>Price - Currently charged to customers.
                        </li>
                        <li>Margin - Based on Adj. Cost and Current Price
                        </li>
                        </ul>
                    </li>
                    <li>Vendor Group
                    <ul>
                    <li>Vendor:Raw Price - Raw Price.
                    It is calculated freshly for display here (unlike SRP, below)
                    from Adj. Cost and the margin in, in order of preference:
                    <ol>
                        <li>An override of the default Store Department Margin for this Vendor.</li>
                        <li>The Vendor Subcategory Margin.
                        (May apply to the all of the vendor\'s items)</li>
                        <li>The Store Department Margin.</li>
                    </ol>
                    It is NOT (unlike SRP) rounded: hence Raw.
                    </li>

                    <li>Vendor:SRP - The new price that will be applied when the Batch is run.
                    <br />It is usually assigned by running Recalculate Vendor SRPs
                    right after loading new data to the Vendor Catalogue,
                    however some loaders assign it as part of the loading.
                    It is not (unlike Raw Price, above) calculated anew for use here.
                    <br />It is calculated from Adj. Cost and the margin in, in order of
                    preference:
                    <ol>
                        <li>An override of the default Store Department Margin for this Vendor.</li>
                        <li>The Vendor Subcategory Margin.
                        (May apply to the all of the vendor\'s items)</li>
                        <li>The Store Department Margin.</li>
                    </ol>
                    and then rounded. For details of the rounding rules see the
                    Help in Recalculate Vendor SRPs.
                    <br />SRP can be edited inline (click).
                    <br />The SRP in the Vendor Catalogue is changed immediately.
                    <br />If the item has already been
                    chosen for the Batch the price in the Batch Item will be changed immediately
                    upon clicking "Save".
                    </li>

                    <li>Vendor:SRP Margin - The actual new margin for this item.
                    <br />It is calculated from the Adj.Cost and Vendor:SRP, i.e. the rounded price.
                    <br />If Vendor:SRP is edited Margin is updated upon "Save".
                    <br />If it is 0.00 it means Vendor:SRP is the same as Adj.Cost or
                    Vendor:SRP hasn\'t been assigned.
                    Run Recalculate Vendor SRPs to update Vendor:SRP and then return to
                    this utility.
                    </li>

                    <li>Vendor:Raw Margin - The margin used, based on the noted priorities,
                    to calculate Raw Price.
                    <ol>
                        <li>An override of the default Store Department Margin for this Vendor.</li>
                        <li>The Vendor Subcategory Margin.
                        (May apply to the all of the vendor\'s items)</li>
                        <li>The Store Department Margin.</li>
                    </ol>
                    The margin the system understands you meant to be used.
                    <br />The initial number 1: 2: or 3: indicates the source of the margin
                    from the prioritized list above, e.g. 3: for Store Department Margin.
                    </li>

                    <li>Vendor:Sub Cat - The Vendor Subcategory, if assigned, if not, 0.
                    </li>
                    </ul>
                    </li>

                    <li>Var. - Variable. If ticked, the price in SRP is treated as an override of the
                    usual method of calculation,
                    i.e. it means "the margin-based price does not apply"
                    <br />The store\'s products list is changed immediately to reflect this,
                    i.e. it does not wait for the Batch to be submitted.
                    <br />If you want to enter details for the override go to the Margin section
                    of Item Maintenance for the item.
                    </li>

                    <li>Batch - Click the "+" icon to add the item to the Batch
                    or the "-" icon to remove it.
                    </li>
                </ul>
                </p>';
            $ret .= '<p>Row Colours
                <ul>
                    <li>White - the usual cost-and-margin-based price calculation doesn\'t apply.
                    <br />The price is said to be Variable.
                    </li>
                    <li>Blue - the item has been chosen for the Batch.
                    </li>
                    <li>Red - Current:Price is more than $.10 <em>less</em> than the
                    Vendor:Raw price calculated from the current Adj. Cost.
                    <br />The price is below margin.
                    </li>
                    <li>Yellow - Current:Price is more than $.10 <em>more</em> than the
                    Vendor:Raw price calculated from the current Adj. Cost.
                    <br />The price is above margin.
                    </li>
                    <li>Green - Current:Price is within $.10 plus or minus the
                    Vendor:Raw price calculated from the current Adj. Cost.
                    <br />The price is at margin.
                    </li>
                </ul>
                </p>';
            $ret .= '<p>Shelf tag items for the items chosen for the Batch are
                automaically created;
                the tags may be printed from the Batch Management page.
                <br />Choose your preferred Shelf Tag Queue on the Form.
                </p>';
        }

        return $ret;
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->get_view()));
        $this->id = 1;
        $phpunit->assertNotEquals(0, strlen($this->get_id_view()));
    }
}

FannieDispatch::conditionalExec();

