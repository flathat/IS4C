<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op
    Copyright 2013 West End Food Co-op, Toronto

    This file is part of Fannie.

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

/**
 *  6Mar16 Eric Lee Updated for API.
 * 19Dec13 Eric Lee Cloned from SyncIndexPage.php for mirroring server data.
*/

/** TODO
 *  6Mar16 Support choosing among mirror/target servers.
 *  6Mar16 Checkbox for specials_only.
 *  6Mar16 Get list of trans_archive tables dynamically, including bigArchive.
*/

include('../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class SyncMirrorIndexPage extends FanniePage
{

	protected $title = "Fannie : Mirror Server Tables";
	protected $header = "Mirror Server Tables";
    public $themed = true;

	function body_content()
	{
		global $FANNIE_MIRRORS;
		ob_start();
		$mlist = "No mirror servers";
		if (isset($FANNIE_MIRRORS) && is_array($FANNIE_MIRRORS)) {
			$mlist = "<ul>";
			foreach ($FANNIE_MIRRORS as $mirror) {
				$mlist .= "<li>{$mirror['host']}</li>";
			}
			$mlist .= "</ul>";
		}
		?>
		<div style="margin:1.0em 0em 1.0em 0em;">Replace the data in the table on servers<?php echo "$mlist" ?> with the data from here.
		</div>
		<form action="TableSyncMirrorPage.php" method="get">

		<b>Table</b>: 
		<select name="tablename">
			<option value="">Select a table</option>

			<option value="">-- Transaction Tables --</option>
			<option value="AR_EOM_Summary">AR_EOM_Summary</option>
			<option value="ar_history">ar_history</option>
			<option value="ar_history_backup">ar_history_backup</option>
<option value="ar_history_sum">ar_history_sum</option>
			<option value="dlog_15">dlog_15</option>
			<option value="dtransactions">dtransactions</option>
			<option value="transarchive">transarchive</option>

			<option value="">-- Operation Tables --</option>

			<option value="">-- Member tables</option>
			<option value="">All member tables</option>
			<option value="custdata">custdata (Members)</option>
<option value="meminfo">meminfo</option>
			<option value="memberCards">memberCards</option>
<option value="memberNotes">memberNotes</option>
<option value="memContact">memContact</option>
<option value="memContactPrefs">memContactPrefs</option>
<option value="memDates">memDates</option>
<option value="custdataBackup">custdataBackup</option>
			<option value="">-- Member Control tables</option>
<option value="memtype">memtype - Control</option>
<option value="memdefaults">memdefaults - Control</option>
<option value="custAvailablePrefs">custAvailablePrefs - Control</option>

			<option value="">-- Suspensions - of AR privs --</option>
<option value="suspensions">suspensions</option>
<option value="suspension_history">suspension_history</option>

			<option value="">-- Main product tables --</option>
			<option value="products">products</option>
			<option value="productUser">productUser</option>
<option value="products_WEFC_Toronto">products_WEFC_Toronto</option>
<option value="prodExtra">prodExtra</option>
			<option value="">-- Other product tables --</option>
<option value="prodDepartmentHistory">prodDepartmentHistory</option>
<option value="prodPriceHistory">prodPriceHistory</option>
<option value="productBackup">productBackup</option>
<option value="prodUpdate">prodUpdate</option>
<option value="prodUpdateArchive">prodUpdateArchive</option>
<option value="prodPhysicalLocation">prodPhysicalLocation</option>

			<option value="">-- Departments --</option>
			<option value="departments">departments</option>
<option value="VendorSpecificMargins">VendorSpecificMargins</option>
<option value="deptMargin">deptMargin</option>
<option value="deptSalesCodes">deptSalesCodes</option>
<option value="superDeptNames">superDeptNames</option>
<option value="superdepts">superdepts</option>
<option value="subdepts">subdepts</option>

			<option value="">-- Vendors --</option>
<option value="vendors">vendors</option>
<option value="vendorItems">vendorItems</option>
<option value="vendorContact">vendorContact</option>
<option value="vendorDepartments">vendorDepartments</option>
<option value="vendorLoadScripts">vendorLoadScripts</option>
<option value="vendorSKUtoPLU">vendorSKUtoPLU</option>
<option value="vendorSRPs">vendorSRPs</option>

			<option value="">-- Other Op Tables --</option>
			<option value="employees">employees - Cashiers</option>
			<option value="tenders">tenders</option>
<option value="taxrates">taxrates</option>

			<option value="">-- Other Op Tables 2 --</option>
<option value="AdSaleDates">AdSaleDates</option>
<option value="batchBarcodes">batchBarcodes</option>
<option value="batchCutPaste">batchCutPaste</option>
<option value="batches">batches</option>
<option value="batchList">batchList</option>
<option value="batchMergeTable">batchMergeTable</option>
<option value="batchowner">batchowner</option>
<option value="batchType">batchType</option>
<option value="cronBackup">cronBackup</option>
<option value="customReceipt">customReceipt</option>
<option value="customReports">customReports</option>
<option value="custPreferences">custPreferences</option>
<option value="custReceiptMessage">custReceiptMessage</option>
<option value="dateRestrict">dateRestrict</option>
<option value="disableCoupon">disableCoupon</option>
<option value="emailLog">emailLog</option>
<option value="houseCouponItems">houseCouponItems</option>
<option value="houseCoupons">houseCoupons</option>
<option value="houseVirtualCoupons">houseVirtualCoupons</option>
<option value="likeCodes">likeCodes</option>
<option value="originCountry">originCountry</option>
<option value="originCustomRegion">originCustomRegion</option>
<option value="origins">origins</option>
<option value="originStateProv">originStateProv</option>
<option value="PurchaseOrder">PurchaseOrder</option>
<option value="PurchaseOrderItems">PurchaseOrderItems</option>
<option value="reasoncodes">reasoncodes</option>
<option value="scaleItems">scaleItems</option>
<option value="shelftags">shelftags</option>
<option value="unfi">unfi</option>
<option value="unfiCategories">unfiCategories</option>
<option value="unfi_order">unfi_order</option>
<option value="upcLike">upcLike</option>
<option value="UpdateLog">UpdateLog</option>
<option value="userGroupPrivs">userGroupPrivs</option>
<option value="userGroups">userGroups</option>
<option value="userKnownPrivs">userKnownPrivs</option>
<option value="userPrivs">userPrivs</option>
<option value="Users">Users</option>
<option value="userSessions">userSessions</option>

			<option value="">-- Other Transaction Tables --</option>
<option value="alog">alog</option>
<option value="CashPerformDay_cache">CashPerformDay_cache</option>
<option value="CompleteSpecialOrder">CompleteSpecialOrder</option>
<option value="dtranstoday">dtranstoday</option>
<option value="efsnetRequest">efsnetRequest</option>
<option value="efsnetRequestMod">efsnetRequestMod</option>
<option value="efsnetResponse">efsnetResponse</option>
<option value="efsnetTokens">efsnetTokens</option>
<option value="InvAdjustments">InvAdjustments</option>
<option value="InvCache">InvCache</option>
<option value="InvDelivery">InvDelivery</option>
<option value="InvDeliveryArchive">InvDeliveryArchive</option>
<option value="InvDeliveryLM">InvDeliveryLM</option>
<option value="InvSalesArchive">InvSalesArchive</option>
<option value="lane_config">lane_config</option>
<option value="PendingSpecialOrder">PendingSpecialOrder</option>
<option value="SpecialOrderContact">SpecialOrderContact</option>
<option value="SpecialOrderDeptMap">SpecialOrderDeptMap</option>
<option value="SpecialOrderHistory">SpecialOrderHistory</option>
<option value="SpecialOrderID">SpecialOrderID</option>
<option value="SpecialOrderNotes">SpecialOrderNotes</option>
<option value="SpecialOrderStatus">SpecialOrderStatus</option>
<option value="stockpurchases">stockpurchases</option>
<option value="suspended">suspended transactions</option>
<option value="valutecRequest">valutecRequest</option>
<option value="valutecRequestMod">valutecRequestMod</option>
<option value="valutecResponse">valutecResponse</option>
<option value="voidTransHistory">voidTransHistory</option>
<option value="reportDataCache">reportDataCache</option>
<option value="sumDeptSalesByDay">sumDeptSalesByDay</option>
<option value="sumDiscountsByDay">sumDiscountsByDay</option>
<option value="sumFlaggedSalesByDay">sumFlaggedSalesByDay</option>
<option value="sumMemSalesByDay">sumMemSalesByDay</option>
<option value="sumMemTypeSalesByDay">sumMemTypeSalesByDay</option>
<option value="sumRingSalesByDay">sumRingSalesByDay</option>
<option value="sumTendersByDay">sumTendersByDay</option>
<option value="sumUpcSalesByDay">sumUpcSalesByDay</option>

			<option value="">-- Archive Tables --</option>
<?php
        /* Get the complete list of Archive Tables
         */
        ?>
			<option value="transArchive201312">transArchive201312</option>
<option value="transArchive201210">transArchive201210</option>
<option value="transArchive201211">transArchive201211</option>
<option value="transArchive201212">transArchive201212</option>
<option value="transArchive201301">transArchive201301</option>
<option value="transArchive201302">transArchive201302</option>
<option value="transArchive201303">transArchive201303</option>
<option value="transArchive201304">transArchive201304</option>
<option value="transArchive201305">transArchive201305</option>
<option value="transArchive201306">transArchive201306</option>
<option value="transArchive201307">transArchive201307</option>
<option value="transArchive201308">transArchive201308</option>
<option value="transArchive201309">transArchive201309</option>
<option value="transArchive201310">transArchive201310</option>
<option value="transArchive201311">transArchive201311</option>
<option value="transArchive201312">transArchive201312</option>
		</select><br /><br />

		<b>Other table</b>: <input type="text" name="othertable" /><br /><br />

        <input type="submit" value="Replace Data" />
        <br /><br />
		</form>
		<?php
		return ob_get_clean();

	// body_content()
	}

    public function helpContent()
    {
        return '<p>Send data from the current server, the source,
            to another server, the target (mirror).
            The mirror operation
            discards current data on the target server and completely replaces it with
            the source server\'s data.</p>
            <p>The <em>Table</em> dropdown contains most of the tables
            but any other table can be sent using the <emOther table</em>
            field.</p>
            ';
    }

// class
}

FannieDispatch::conditionalExec(false);

?>
