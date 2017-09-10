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
/*
 * This uses $FANNIE_PO_COOP_VENDOR_ID to get address information
 *  for the ordering Co-op from the Vendors table.
 * There is no Install-level UI for it yet, has to be hand entered
 *  in config.php.
 */

class WEFC_Toronto_PdfPoExport_WEFC {

    public $nice_name = 'WEFC Toronto PDF';
    public $extension = 'pdf';
    public $mime_type = 'application/pdf';

    function send_headers(){
    }

    function export_order($id){
        global $FANNIE_OP_DB, $FANNIE_ROOT,
            $FANNIE_PO_COOP_VENDOR_ID;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        
        $order = new PurchaseOrderModel($dbc);
        $order->orderID($id);
        $order->load();

        $items = new PurchaseOrderItemsModel($dbc);
        $items->orderID($id);

        /* EL: Why did I comment this out?
        $notes = new PurchaseOrderNotesModel($dbc);
        $notes->orderID($id);
        $notes->load();
        $noteContent = trim($notes->notes());
         */

        if (!class_exists('FPDF')) {
            include_once($FANNIE_ROOT.'src/fpdf/fpdf.php');
        }
        $pdf = new FPDF('P','mm','Letter');
        $pdf->AddPage();
    
        $pdf->SetFont('Arial','','12');
        $pdf->Cell(100, 5, 'Order Date: '.date('F j, Y'), 0, 0);
        $pdf->Cell(100, 5, 'Purchase Order#: '.$order->vendorOrderID(), 0, 0);
        $pdf->Ln();
        $pdf->Ln();

        /* The Vendor record for the ordering organization.
         */
        $coop_vendor_id = 0;
        if (isset($FANNIE_PO_COOP_VENDOR_ID)) {
            $coop_vendor_id = $FANNIE_PO_COOP_VENDOR_ID;
            $vendor = new VendorsModel($dbc);
            $vendor->vendorID($coop_vendor_id);
            $vendor->load();

            $contact = new VendorContactModel($dbc);
            $contact->vendorID($coop_vendor_id);
            $contact->load();

            $pdf->SetFont('Arial','','14');
            $pdf->Cell(30, 6, 'Ship To:', 0, 0);
            $pdf->SetX(35);
            $abc = "placeholder";
            $pdf->Cell(70, 6, $vendor->vendorName(), 0, 0);
            $pdf->Ln();
            $pdf->SetX(35);
            $pdf->Cell(100, 6, ''.$vendor->address(), 0, 0);
            $pdf->Ln();
            $city_prov_postal_code = sprintf('%s, %s  %s',
                $vendor->city(),
                $vendor->state(),
                $vendor->zip()
            );
            $pdf->SetX(35);
            $pdf->Cell(100, 6, ''.$city_prov_postal_code, 0, 0);
            $pdf->Ln();
            $pdf->Ln();
            $pdf->SetFont('Arial','','12');
            $pdf->Cell(100, 5, 'Phone: '.$contact->phone(), 0, 0);
            $pdf->Cell(100, 5, 'Fax: '.$contact->fax(), 0, 0);
            $pdf->Ln();
            $pdf->Cell(100, 5, 'Email: '.$contact->email(), 0, 0);
            $pdf->Cell(100, 5, 'Website: '.$contact->website(), 0, 0);
            $pdf->Ln();
            $pdf->MultiCell(0, 5, "Delivery Info:\n".$contact->notes(), 'B');
            $pdf->Ln();
        }

        /* The Vendor being ordered from.
         */
        $vendor = new VendorsModel($dbc);
        $vendor->vendorID($order->vendorID());
        $vendor->load();

        $contact = new VendorContactModel($dbc);
        $contact->vendorID($order->vendorID());
        $contact->load();

        $pdf->SetFont('Arial','','12');
        $pdf->Cell(100, 5, 'Vendor: '.$vendor->vendorName(), 0, 0);
        $pdf->Ln();
        $pdf->Cell(100, 5, 'Phone: '.$contact->phone(), 0, 0);
        $pdf->Cell(100, 5, 'Fax: '.$contact->fax(), 0, 0);
        $pdf->Ln();
        $pdf->Cell(100, 5, 'Email: '.$contact->email(), 0, 0);
        $pdf->Cell(100, 5, 'Website: '.$contact->website(), 0, 0);
        $pdf->Ln();
        $pdf->MultiCell(0, 5, "Ordering Info:\n".$contact->notes(), 'B');
        $pdf->Ln();

        $cur_page = 0;
        $pdf->SetFontSize(10);
        $line_item_cost = 0.0;
        $total_cost = 0.0;
        foreach($items->find() as $obj){
            /* Column heads
             */
            if ($cur_page != $pdf->PageNo()){
                $cur_page = $pdf->PageNo();
                $pdf->Cell(25, 5, 'SKU', 0, 0);
                $pdf->Cell(20, 5, 'Cases', 0, 0, 'C');
                //$pdf->Cell(20, 5, 'Order Qty', 0, 0);
                $pdf->Cell(20, 5, 'Units/Case', 0, 0);
                $pdf->Cell(30, 5, 'Brand', 0, 0);
                $pdf->Cell(65, 5, 'Description', 0, 0);
                /* Renamed and moved before Brand
                 * $pdf->Cell(20, 5, 'Case Size', 0, 0);
                 */
                $pdf->Cell(20, 5, 'Est. Cost', 0, 0, 'R');
                $pdf->Ln();
            }

            $pdf->Cell(25, 5, $obj->sku(), 0, 0);
            $pdf->Cell(20, 5, $obj->quantity(), 0, 0, 'C');
            $pdf->Cell(20, 5, $obj->caseSize(), 0, 0, 'C');
            $pdf->Cell(30, 5, $obj->brand(), 0, 0);
            $desc = $obj->description();
                $desc .= ($obj->unitSize() != '') ? ", {$obj->unitSize()}" : '';
            $pdf->Cell(65, 5, $desc, 0, 0);
            //$pdf->Cell(20, 5, $obj->caseSize(), 0, 0, 'C');
            $line_item_cost = ($obj->caseSize()*$obj->unitCost()*$obj->quantity());
            $total_cost += $line_item_cost;
            $pdf->Cell(20, 5, sprintf('%.2f',$line_item_cost), 0, 0, 'R');
            $pdf->Ln();
        }
        $pdf->SetFontSize(12);
        $pdf->Cell(25, 6, _("Est. Total"), 0, 0);
        $pdf->SetX(170);
        $pdf->Cell(20, 6, sprintf('$ %.2f',$total_cost), 0, 0, 'R');
        $pdf->Ln();

        $pdf->Output('WEFC_order_export.pdf', 'D');
    }
}

