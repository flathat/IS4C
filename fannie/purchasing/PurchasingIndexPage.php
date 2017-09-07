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

class PurchasingIndexPage extends FannieRESTfulPage 
{
    protected $header = 'Purchase Orders';
    protected $title = 'Purchase Orders';

    public $description = '[Purchase Order Menu] lists purchase order related pages.';

    protected $must_authenticate = true;

    protected function put_handler()
    {
        try {
            $vendors = $this->form->vendors;
            $vendors = array_filter($vendors, function ($i) { return $i != ''; });
            $vendors = array_unique($vendors);
            if (count($vendors) == 0) {
                throw new Exception('At least one vendor required');
            }
            if (!class_exists('OrderGenTask')) {
                include(dirname(__FILE__) . '/../cron/tasks/OrderGenTask.php');
            }
            $task = new OrderGenTask();
            $task->setConfig($this->config);
            $task->setLogger($this->logger);
            $task->setMultiplier($this->form->multiplier);
            $task->setSilent(true);
            $task->setVendors($vendors);
            $task->setUser(FannieAuth::getUID($this->current_user));
            $task->setStore($this->form->store);
            $task->run();

            return 'ViewPurchaseOrders.php?init=pending';
        } catch (Exception $ex) {
            return true;
        }
    }

    protected function put_view()
    {
        $stores = FormLib::storePicker('store', false);
        $res = $this->connection->query('
            SELECT v.vendorID, v.vendorName
            FROM vendors AS v
                INNER JOIN vendorItems AS i ON v.vendorID=i.vendorID
                INNER JOIN InventoryCache AS c ON i.upc=c.upc
                INNER JOIN products AS p ON c.upc=p.upc AND c.storeID=p.store_id
            WHERE i.vendorID=p.default_vendor_id
                AND v.inactive=0
            GROUP BY v.vendorID, v.vendorName
            ORDER BY v.vendorName');
        $vendorSelect = '<select class="form-control chosen" name="vendors[]">';
        $vendorSelect .= '<option value="">Select vendor...</option>';
        while ($row = $this->connection->fetchRow($res)) {
            $vendorSelect .= sprintf('<option value="%d">%s</option>', $row['vendorID'], $row['vendorName']);
        }
        $vendorSelect .= '</select>';
        $ret = '<form>
            <input type="hidden" name="_method" value="put" />
            <div class="small panel panel-default">
                <div class="panel panel-heading">Vendors & Stores</div>
                <div class="panel panel-body">
            <label>Vendor(s)</label>';
        for ($i=0; $i<5; $i++) {
            $ret .= '<div class="form-group">' . $vendorSelect . '</div>'; 
        }
        $ret .= '
            <p>
                <label>Store</label>
                ' . $stores['html'] . '
            </p>
            </div></div>
            <div class="small panel panel-default">
                <div class="panel panel-heading">Automated Pars</div>
                <div class="panel panel-body">
                    <div class="form-group">
                        <label title="For use with produce plants">Multiplier (optional)</label>
                        <input class="form-control" type="number" value="1" min="1" max="30" name="multiplier" />
                    </div>
                    <div class="form-group">
                        <label title="For use with produce plants">Forecast (optional)</label>
                        <div class="input-group">
                            <span class="input-group input-group-addon">$</span>
                            <input class="form-control" type="forecast" value="0" min="0" max="1000000" name="forecast" />
                        </div>
                    </div>
                </div>
            </div>
            <p>
                <button type="submit" class="btn btn-default btn-core">Generate Orders</button>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button type="reset" class="btn btn-default" 
                    onclick="$(\'select.chosen\').val(\'\').trigger(\'chosen:updated\');">Reset</button>
            </p>
        </form>';

        $this->addScript('../src/javascript/chosen/chosen.jquery.min.js');
        $this->addCssFile('../src/javascript/chosen/bootstrap-chosen.css');
        $this->addOnloadCommand("\$('select.chosen').chosen();\n");

        return $ret;
    }

    /* EL Various changes
     * Notes on Generate Orders and Store Transfer
     * Reports section
     */
    protected function get_view()
    {

        return '<ul>
            <li><a href="ViewPurchaseOrders.php">Orders: View, Edit, Receive</a>
            <li><a href="PurchasingSearchPage.php">Search for Orders</a>
            <li>Create New Order or Continue Current Order
                <ul>
                <li><a href="EditOnePurchaseOrder.php">For One Vendor</a></li>
                <li><a href="EditManyPurchaseOrders.php">By Item, for One or More Vendors</a></li>
                <li>Generate Orders (Available after full upgrade)</li>
                <li>Store Transfer (Available after full upgrade)</li>
                <!--
                <li><a href="?_method=put">Generate Orders</a></li>
                <li><a href="ScanTransferPage.php">Store Transfer</a></li>
                -->
                </ul>
            </li>
            <li>Import Order
                <ul>
                    <li><a href="ManualPurchaseOrderPage.php">Manually</a></li>
                    <li><a href="ImportPurchaseOrder.php">From Spreadsheet</a></li>
                </ul>
            </li>
            <li>Reports
                <ul>
                <!-- li><a href="reports/LocalInvoicesReport.php">Local Invoices</a></li -->
                <li><a href="reports/OutOfStockReport.php">Vendor Out of Stock</a></li>
                <li><a href="reports/TotalPurchasesReport.php">Orders Placed and Total Purchases</a></li>
                </ul>
            </li>
            </ul>';
        
    }

    /* EL: WEFC version
     */
    public function helpContent()
    {
        $ret = '';
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $ret .= '<p>Purchase Orders are for managing incoming inventory,
                i.e. items the store purchases from a vendor for sale to
                customers.
                They are used to manage both the Ordering and Receiving
                of items.
                </p>
                <p>
                Creating Purchase Orders depends on vendor data, especially
                vendor item catalogs; only items in a vendor catalog can be
                added to a purchase order.
                <br />Items that are only known in the Products list cannot
                be added to an Order.  New items that are entered using the
                Item Maintenance module are ordinarily added to the Vendor
                Catalog as well as the Products List,
                but only if the Vendor is identified at the time (or later).
                </p>

                <p>Purchase Orders include two separate sets of quantity
                and cost fields. One set is for the number of items ordered
                and expected cost. The other set is for the number of items
                acutally received and received cost.
                </p>

                <p>Ordering Scenarios
                <ul>
                <li>In-aisle.
                The buyer is on the floor of the store, with a computer or
                handheld device and probably a handheld scanner,
                usually scanning barcodes from shelftags or items.
                Lookups are based on UPCs since SKUs are not usually available.
                </li>At a desk/in an office.
                Typing in SKUs, or UPCs, since barcodes are not likely available,
                or picking items from a list.
                <li>
                </li>
                <li>Import.
                This usually implies reading spreadsheet or PDF data from a
                Vendor\'s Invoice or Manifest in order to get information
                about what has been Received.
                It skips the Ordering part of the process
                and creates a Purchase Order in the system with only one
                set of quantities and costs, the Received set.
                </li>
                <li>Manual.
                I don\'t understand this yet.
                Something to do with items that are not in a Vendor Catalog?
                </li>
                </ul>
                </p>

                <p>The life cycle of an Order has three stages:
                <OL>
                <li>Pending (or Open).
                This is the status from when the Order is created and while
                items are being added to it.
                </li>
                <li>Placed (or Closed).
                When all items have been added the status is changed to Placed
                in preparation for sending the Purchase Order to the Vendor.
                Since the Order will be compared to the shipment from the Vendor
                items should not be added to it after it has been placed
                (unless they were also added at the Vendor\'s end).
                </li>
                <li>Received.
                When the shipment is received from the Vendor the Order is compared
                to the Invoice (or Manifest) from the Vendor
                and the received quantities and costs noted.
                <br />- If perpetual inventory is being done,
                items that are Received are added to Inventory in an overnight process
                (i.e. NOT at the moment they are entered as received).
                <br />- The "Received" status begins when the first item is recieved.
                It does not have a formal designation in the system
                as Pending and Placed do.
                </OL>
                </p>

                <p>Menu Options
                <ul>
                <li>Orders: View, Edit, Receive.
                This is the way to resume work on an existing order.
                If you just want to continue adding items to a Pending order
                or orders
                it is easiest to start from one of the Create options, below.
                </li>
                <li>Search is for finding Orders, either Pending or Placed,
                that contain a certain item, identified by SKU or UPC.
                The search can be restricted to a range of dates.
                </li>
                <li>Import creates a purchase order from a spreadsheet.
                </li>
                <li>Creating orders:
                    <ul>
                        <li>For a Single Vendor.
                        <br />Only items (identified by UPC or SKU) from the catalog
                           of the chosen vendor can be added.
                           <br />If there is a pending order for the vendor items will
                           be added to it; if there is not
                           (i.e. if this is the first order or 
                           all previous orders have been Placed)
                           a new order will be created.
                           <br />It is not possible to have two pending orders for the
                           same vendor.
                        </li>
                        <li>Creating orders by item will match UPCs and SKUs
                        from all known vendors. This option creates separate orders
                        for each vendor as needed.
                            </li>
                        <li>Generate Orders creates a draft Order using On-hand and Pars
                            values.
                        </li>
                        <li>Store Transfer is for moving items between the inventories
                            of two stores within an organization.
                        </li>
                    </ul>
                    </li>
                </ul>
                </p>
                ';

        } else {
            $ret .= '<p>Purchase Orders are for incoming inventory - i.e., 
                items the store purchases from a vendor and then sells to
                customers. Purchase Orders depend on vendor data and especially
                vendor item catalogs. Only items in a vendor catalog can be
                added to a purchase order.</p>
                <p>Purchase Orders may include two separate sets of quantity
                and cost fields. One set is for the number of items ordered
                and expected cost. The other set is for the number of items
                acutally received and received cost.</p>
                <p>View and Search are straightforward. Import creates a purchase
                order from a spreadsheet. Creating orders by vendor results
                in a single order. Only UPCs and SKUs from the chosen vendor
                can be added. Creating orders by item will match UPCs and SKUs
                from all known vendors. This option creates separate orders
                for each vendor as needed.</p>
                ';
        }

        return $ret;
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->get_view()));
        $phpunit->assertNotEquals(0, strlen($this->put_view()));
    }
}

FannieDispatch::conditionalExec();

