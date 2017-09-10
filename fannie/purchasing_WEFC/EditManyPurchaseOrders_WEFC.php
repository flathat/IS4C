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

include(dirname(__FILE__) . '/../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class EditManyPurchaseOrders_WEFC extends FannieRESTfulPage 
{
    protected $header = 'Purchase Orders WEFC';
    protected $title = 'Purchase Orders WEFC';

    public $description = '[Multi-Vendor Purchase Order WEFC] creates and edits multiple purchase orders
    as items from different vendors are scanned.';

    protected $must_authenticate = true;
    private $page_suffix = '';
    private $self_page = '';
    private $home_page = '';

    function preprocess()
    {
        $this->__routes[] = 'get<search>';
        $this->__routes[] = 'get<id><sku><qty>';
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $this->enable_linea = false;
            $this->header = 'Purchase Orders for One-or-More Vendors';
            $this->title = 'PO-Many Vendors';
        }
        $this->self_page = filter_input(INPUT_SERVER, 'PHP_SELF');
        $this->page_suffix = '_WEFC';
        $this->home_page = "PurchasingIndexPage{$this->page_suffix}.php";
        return parent::preprocess();
    }

    protected function get_search_handler()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $ret = array(); 

        // search by vendor SKU
        $skuQ = 'SELECT brand, description, size, units, cost, sku,
            i.vendorID, vendorName
            FROM vendorItems AS i LEFT JOIN vendors AS v ON
            i.vendorID=v.vendorID WHERE sku LIKE ?';
        $skuP = $dbc->prepare($skuQ);
        $skuR = $dbc->execute($skuP, array('%'.$this->search.'%'));
        while($w = $dbc->fetch_row($skuR)){
            $result = array(
            'sku' => $w['sku'],
            'title' => '['.$w['vendorName'].'] '.$w['brand'].' - '.$w['description'],
            'unitSize' => $w['size'],   
            'caseSize' => $w['units'],
            'unitCost' => sprintf('%.2f',$w['cost']),
            'caseCost' => sprintf('%.2f',$w['cost']*$w['units']),
            'vendorID' => $w['vendorID']
            );
            $ret[] = $result;
        }
        if (count($ret) > 0){
/*
$j_echoed = json_encode($ret);
$dbc->logger("Many::get_search_handler() j_echoed: $j_echoed");
 */
            echo json_encode($this->addCurrentQty($dbc, $ret));
            //echo json_encode($ret);
            return False;
        }

        // search by UPC
        $upcQ = 'SELECT brand, description, size, units, cost, sku,
            i.vendorID, vendorName
            FROM vendorItems AS i LEFT JOIN vendors AS v ON
            i.vendorID = v.vendorID WHERE upc=?';
        $upcP = $dbc->prepare($upcQ);
        $upcR = $dbc->execute($upcP, array(BarcodeLib::padUPC($this->search)));
        while($w = $dbc->fetch_row($upcR)){
            $result = array(
            'sku' => $w['sku'],
            'title' => '['.$w['vendorName'].'] '.$w['brand'].' - '.$w['description'],
            'unitSize' => $w['size'],   
            'caseSize' => $w['units'],
            'unitCost' => sprintf('%.2f',$w['cost']),
            'caseCost' => sprintf('%.2f',$w['cost']*$w['units']),
            'vendorID' => $w['vendorID']
            );
            $ret[] = $result;
        }
        if (count($ret) > 0){
            echo json_encode($this->addCurrentQty($dbc, $ret));
            //echo json_encode($ret);
            return False;
        }

        echo '[]';
        return False;
    }

    private function addCurrentQty($dbc, $results)
    {
        $idCache = array();
        $uid = FannieAuth::getUID($this->current_user);
        $lookupP = $dbc->prepare('SELECT quantity FROM PurchaseOrderItems WHERE orderID=? AND sku=?');
        for ($i=0; $i<count($results); $i++) {
            $vendorID = $results[$i]['vendorID'];
            $sku = $results[$i]['sku'];
            if (isset($idCache[$vendorID])) {
                $orderID = $idCache[$vendorID];
            } else {
                $orderID = $this->getOrderID($vendorID, $uid);
                $idCache[$vendorID] = $orderID;
            }
            $qty = $dbc->getValue($lookupP, array($orderID, $sku));
            $results[$i]['currentQty'] = $qty === false ? 0 : $qty;
        }

        return $results;
    }

    /**
      AJAX call: ?id=<vendor ID>&sku=<vendor SKU>&qty=<# of cases>
      Add the given SKU & qty to the order

      EL: Heading and Home button with calculate_sidebar.
    */
    protected function get_id_sku_qty_handler()
    {
        global $FANNIE_OP_DB;

        $dbc = FannieDB::get($FANNIE_OP_DB);
        $orderID = $this->getOrderID($this->id, FannieAuth::getUID($this->current_user));
$dbc->logger("EM:gisqh id: " . $this->id . " sku: " . $this->sku . " qty: " . $this->qty);

        $vitem = new VendorItemsModel($dbc);
        $vitem->vendorID($this->id);
        $vitem->sku($this->sku);
        $vitem->load();

        $pitem = new PurchaseOrderItemsModel($dbc);
        $pitem->orderID($orderID);
        $pitem->sku($this->sku);
        $pitem->quantity($this->qty);
        $pitem->unitCost($vitem->cost());
        $pitem->caseSize($vitem->units());
        $pitem->unitSize($vitem->size());
        $pitem->brand($vitem->brand());
        $pitem->description($vitem->description());
        $pitem->internalUPC($vitem->upc());
    
        $pitem->save();

        $ret = array();
        $pitem->reset();
        $pitem->orderID($orderID);
        $pitem->sku($this->sku);
        if (count($pitem->find()) == 0){
            $ret['error'] = 'Error saving entry';
        } else {
            $sidebar = '<p><strong>Pending Orders</strong></p>';
            $sidebar .= $this->calculate_sidebar();
            $sidebar .= '<button class="btn btn-default" onclick="location=\'' .
                $this->home_page . '\'; return false;">Home</button>';
            $ret['sidebar'] = $sidebar;
            //$ret['sidebar'] = $this->calculate_sidebar();
        }
        echo json_encode($ret);
        return false;
    }

    /* EL AND p.placed =0
     * EL "Order #"
     * EL Link/drill to Order
     */
    protected function calculate_sidebar()
    {
        global $FANNIE_OP_DB;
        $userID = FannieAuth::getUID($this->current_user);

        $dbc = FannieDB::get($FANNIE_OP_DB);
        $q = 'SELECT p.orderID, vendorName, 
            sum(case when i.orderID is null then 0 else 1 END) as rows, 
            MAX(creationDate) as date,
            sum(unitCost*caseSize*quantity) as estimatedCost
            FROM PurchaseOrder as p 
            INNER JOIN vendors as v ON p.vendorID=v.vendorID
            LEFT JOIN PurchaseOrderItems as i
            ON p.orderID=i.orderID
            WHERE p.userID=?
                AND p.placed=0
            GROUP BY p.orderID, vendorName
            ORDER BY vendorName';
        $p = $dbc->prepare($q);
        $r = $dbc->execute($p, array($userID));  

        $ret = '<ul id="vendorList">';
        while($w = $dbc->fetch_row($r)){
            $ret .= '<li><span id="orderInfoVendor">'.$w['vendorName'].'</span>';
            if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
                $ret .= '<ul class="vendorSubList"><li>' .
                    '<a href="' . $this->config->get('URL') . 
                    'purchasing' . $this->page_suffix . '/ViewPurchaseOrders' . $this->page_suffix .
                    '.php?id=' . $w['orderID'] . '"' .
                    ' target="PO_' . $w['orderID'] . '">' .
                    'Order #' . $w['orderID'].' '.$w['date'] . '</a>';
                //$ret .= '<ul class="vendorSubList"><li>Order #'.$w['orderID'].' '.$w['date'];
            } else {
                $ret .= '<ul class="vendorSubList"><li>'.$w['date'];
            }
            $ret .= '<li># of Items: <span class="orderInfoCount">'.$w['rows'].'</span>';
            $ret .= '<li>Est. cost: $<span class="orderInfoCost">'.sprintf('%.2f',$w['estimatedCost']).'</span>';
            $ret .= '</ul></li>';
        }
        $ret .= '</ul>';

        return $ret;
    }

    /*
     *  EL $ret .= '<p><strong>Pending Orders</strong></p>';
     *  EL $ret .= Home button
     *  col-sm-4, not 6
     *    Needs -4 to keep descriptions on one line
     *  sku/upc input width 200px
     *  cases input width 75px instead of size=3
     */
    protected function get_view()
    {
        $ret = '<div class="col-sm-4">';
        $ret .= '<div id="ItemSearch">';
        $ret .= '<form class="form" action="" onsubmit="itemSearch();return false;">';
        $ret .= '<label>UPC/SKU</label><input style="width:200px;" class="form-control" type="text" id="searchField" />';
        //$ret .= '<label>UPC/SKU</label><input class="form-control" type="text" id="searchField" />';
        $ret .= '<button type="submit" class="btn btn-default">Search</button>';
        $ret .= '</form>';
        $ret .= '</div>';
        $ret .= '<p><div id="SearchResults"></div></p>';
        $ret .= '</div>';

        $ret .= '<div class="col-sm-6" id="orderInfo">';
        $ret .= '<p><strong>Pending Orders</strong></p>';
        $ret .= $this->calculate_sidebar();
        $ret .= '<button class="btn btn-default" onclick="location=\'' . $this->home_page .
            '\'; return false;">Home</button>';
        $ret .= '<p> &nbsp; </p>';
        $ret .= '</div>';
        /* EL is an empty col-sm-N needed to fill the page to 12? */

        $this->add_onload_command("\$('#searchField').focus();\n");
        $this->add_script('js/editmany' . $this->page_suffix . '.js');
        //was:$this->add_script('js/editmany.js');
    
        return $ret;
    }

    /**
      Utility: find orderID from vendorID and userID
      EL: Assign and use $default_store to assign storeID
      EL: Assign $po_prefix and use to assign vendorOrderID
    */
    private function getOrderID($vendorID, $userID)
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $orderQ = 'SELECT orderID FROM PurchaseOrder WHERE
            vendorID=? AND userID=? and placed=0
            ORDER BY creationDate DESC';
        $orderP = $dbc->prepare($orderQ);
        $orderR = $dbc->execute($orderP, array($vendorID, $userID));
        if ($dbc->num_rows($orderR) > 0){
            $row = $dbc->fetch_row($orderR);
            return $row['orderID'];
        } else {
            /*
            $insQ = 'INSERT INTO PurchaseOrder (vendorID, creationDate,
                placed, userID) VALUES (?, '.$dbc->now().', 0, ?)';
            $insP = $dbc->prepare($insQ);
            $insR = $dbc->execute($insP, array($vendorID, $userID));
            return $dbc->insertID();
             */
            $insQ = 'INSERT INTO PurchaseOrder (vendorID, creationDate,
                placed, userID, storeID) VALUES (?, '.$dbc->now().', 0, ?, ?)';
            $insP = $dbc->prepare($insQ);
            $default_store = $this->config->get('STORE_ID');
            $po_prefix = $this->config->get('PO_PREFIX','');
            $store = COREPOS\Fannie\API\lib\Store::getIdByIp($default_store);
            $insR = $dbc->execute($insP, array($vendorID, $userID, $store));
            $insertID = $dbc->insertID();
            if (!empty($po_prefix)) {
                $poQ = "UPDATE PurchaseOrder SET vendorOrderID = ? WHERE orderID = ?";
                $poP = $dbc->prepare($poQ);
                $poArgs = array("{$po_prefix}{$insertID}", $insertID);
                $poR = $dbc->execute($poP,$poArgs);
            }
            return $insertID;
        }
    }

    public function helpContent()
    {
        $ret = '';
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $ret .= '
            <p>Enter UPCs or SKUs. If there are multiple matching items,
            use the dropdown to specify which. Then enter the number
            of cases to order.
            </p>
            <p>On the right side of the page
            summaries of all current Pending orders are listed.
            As each item is entered the summary for the order the item
            was added to is updated.
            </p>
            <p>If this is the first item for a vendor
            a new Pending order is automatically created.
            </p>
            <p>Original: Each time you select an item from a different vendor,
            a pending order is automatically created for that vendor
            if one does not already exist.</p>';
        } else {
            $ret .= '
            <p>Enter UPCs or SKUs. If there are multiple matching items,
            use the dropdown to specify which. Then enter the number
            of cases to order.</p>
            <p>Each time you select an item from a different vendor,
            a pending order is automatically created for that vendor
            if one does not already exist.</p>';
        }

        return $ret;
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->get_view()));
        $this->search = '4011';
        ob_start();
        $this->get_search_handler();
        $phpunit->assertInternalType('array', json_decode(ob_get_clean(), true));
        $this->id = 1;
        $this->sku = '4011';
        $this->qty = 1;
        ob_start();
        $this->get_id_sku_qty_handler();
        $phpunit->assertInternalType('array', json_decode(ob_get_clean(), true));
    }
}

FannieDispatch::conditionalExec();

