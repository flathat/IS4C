<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op, Duluth, MN

    This file is part of Fannie.

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
// NOT COMPLETE
/*
 *  7Jul16 EL Use namespaced MemberModule to keep it from being listed twice.
 * 20Apr14 EL Change from changes to A/R to specialized Prepay.
 * 27Mar14 EL Several changes to support "Pay Forward" use at WEFC_Toronto
 * For bootstrapped v.2 re-code the table with floating divs.
 */

class Prepay extends \COREPOS\Fannie\API\member\MemberModule
{

    protected $moduleHeading = 'Prepay';

    public function width()
    {
        return parent::META_WIDTH_HALF;
    }

	function ShowEditForm($memNum,$country="US"){
		global $FANNIE_URL,$FANNIE_TRANS_DB,$FANNIE_COOP_ID;

		$dbc = $this->db();
		$trans = $FANNIE_TRANS_DB.$dbc->sep();
		if (!class_exists("CustdataModel")) {
			include($FANNIE_ROOT.'classlib2.0/data/models/CustdataModel.php');
        }
		$infoQ = $dbc->prepare("SELECT n.balance
				FROM {$trans}ar_live_balance AS n 
				WHERE n.card_no=?");
		$infoR = $dbc->execute($infoQ,array($memNum));
		$infoW = $dbc->fetchRow($infoR);

        $cdModel = new CustdataModel($dbc);
        $cdModel->CardNo($memNum);
        $cdModel->personNum(1);
        $cdModel->load();
        if (method_exists($cdModel,"ChargeLimit")) {
            $limit = $cdModel->ChargeLimit();
        } elseif (method_exists($cdModel,"MemDiscountLimit")) {
            $limit = $cdModel->MemDiscountLimit();
        } else {
            $limit = 0;
        }

        $ret = "";
        $ret .= "<style type='text/css'>
.MemFormTable th {
    font-size: 75%;
	text-align: right;
	color: #fff;
	padding: 0 5px 0 2px;
	border: solid white 2px;
}
.MemFormTable td {
	padding: 2px 2px 2px 2px;
}
</style>";

        $upp = ($cdModel->ChargeOk() == 1) ? ' CHECKED ' : '';
        $fsDisplay = ($cdModel->ChargeOk() == 1 && $limit > 0) ? 'none' : 'block';

        $ret .= "<div class='panel panel-default' style='display:{$fsDisplay};'>";
        $ret .= "<div class='panel-heading'>" . $this->moduleHeading;

        $ret .= "<a onclick=\"$('#_fhtt14042002').toggle();return false;\" href=''>" .
            "<img title='Let the Member make purchases against money in their account " .
            "(Click for more)' src='{$FANNIE_URL}src/img/buttons/help16.png'>" .
            "</a>";
        $ret .= "</div><!-- /.panel-heading -->";
        $ret .= "<div class=\"panel-body\">";

        /* For bootstrapped v.1 retain the table coding.
         */
		$ret .= "<table class='MemFormTable' border='0' width='100%'>";

        $ret .= "<tr>";
		$ret .= "<th title='Allow/prevent the member to Prepay.'>Prepay OK</th>";
		$ret .= sprintf('<td><input type="checkbox" name="use_p_p" %s />
                </td>',$upp);

		$ret .= "<th>Current Balance</th>";
		$ret .= sprintf('<td>%.2f</td>',($infoW['balance'] * -1));	

        $ret .= "<td><a href='{$FANNIE_URL}reports/AR/index.php?memNum=$memNum'>".
            "History</a></td></tr>";

        $ret .= "<tr>";
        $ret .= "<th title='For Prepay Limit is 0.'>Limit</th>";
		$ret .= sprintf('<td> %d </td>',$limit);

        $ret .= "<td colspan='1'><a href='{$FANNIE_URL}" .
            "mem/correction_pages/MemArTransferTool.php?memIN=$memNum'>" .
            "Transfer Prepay</a></td>";
        $ret .= "<td colspan='2'><a href='{$FANNIE_URL}" .
            "mem/correction_pages/MemArEquitySwapTool.php?memIN=$memNum'>" .
            "Convert Prepay</a></td></tr>";

        $ret .= "</table>";
        $ret .= "</div><!-- /.panel-body -->";

        $ret .= '<fieldset id="_fhtt14042002" style="display:none; width:440px;">' .
            "Let the Member make purchases against money in their account. ";
        $ret .= "<br />'Balance' shows how much is left in the account.";
        $ret .= "<br />Un-ticking 'OK' will suspend the account when member data " .
            "is refreshed on lanes. ";
        $ret .= "</fieldset>";
		$ret .= sprintf('<input type="hidden" name="PP_limit" value="%d" />',$limit);
        $ret .= sprintf("<input type='hidden' name='pp_orig_values' value='%d|%d' />",
                ($upp == '' ? 0 : 1), $limit);
        $ret .= "</div><!-- /.panel .panel-default -->";

		return $ret;
	}

	function SaveFormData($memNum){
		global $FANNIE_ROOT;
		$dbc = $this->db();
		if (!class_exists("CustdataModel")) {
			include($FANNIE_ROOT.'classlib2.0/data/models/CustdataModel.php');
        }

        $charge_ok = (FormLib::get_form_value('use_p_p','') == '') ? 0 : 1;
		$limit = FormLib::get_form_value('PP_limit',0);
        list($orig_ok, $orig_limit) =
            explode('|', FormLib::get_form_value('pp_orig_values',''));
        // Enforce the Prepay limit.
        if ($charge_ok == 1 && $orig_ok == 0) {
            $limit = 0;
        }
        // Change only if changed in this sub-form.
        if ("{$charge_ok}|{$limit}" != FormLib::get_form_value('pp_orig_values','')) {
            if ($charge_ok && $limit > 0) {
                return '<p class="memMessage">**Problem in Prepay: Prepay accounts may not have Limit of more than 0.' .
                    '<br />Un-tick OK and fix in another module or see the System Administrator.</p>';
            }
            $cdModel = new CustdataModel($dbc);
            $cdModel->CardNo($memNum);
            $test = false;
            // All the personNum's of this CardNo
            foreach($cdModel->find() as $obj) {
                $obj->ChargeOk($charge_ok);
                if (method_exists($obj,"MemDiscountLimit")) {
                    $obj->MemDiscountLimit($limit);
                }
                if (method_exists($obj,"ChargeLimit")) {
                    $obj->ChargeLimit($limit);
                }
                $obj->ChargeOk($charge_ok);
                $test = $obj->save();
                if ($test)
                    $dbc->logger("Done one $charge_ok for $memNum from pp, test: ok");
                else
                    $dbc->logger("Done one $charge_ok for $memNum from pp, test: failed");
            }

            if ($test === False) {
                return 'Error: Problem saving Prepay limit<br />';
            } else {
                return '';
            }
        } else {
            return '';
        }
	}
}

?>
