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
/* COMMENTZ
 * 12Jan15 EL Start-date of 30 days ago in link to Activity Report.
 * 23May14 EL Support Program/Bank account as for Coop Cred.
 * 20Apr14 EL Change from changes to A/R to specialized Prepay.
 * 27Mar14 EL Several changes to support "Pay Forward" use at WEFC_Toronto
 */
/* TODO
 * 12Jan15 EL The "program" support was a wrong turn. Remove it.
 */

//class StoreCharge extends MemberModule {}
class StoreCharge extends \COREPOS\Fannie\API\member\MemberModule {

    // Where should this be or be gotten from? db? FANNIE_x
    // From coop_cred.programs.ProgramId
    protected $programNumber = 1;
    //$programBankNumber = getBanker($programNumber);
    protected $programBankNumber = 99989;

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
        // $limit must be integer here, but is decimal in the database
        $cols = $cdModel->getColumns();
        if (array_key_exists("ChargeLimit", $cols)) {
            $limit = $cdModel->ChargeLimit();
        } elseif (array_key_exists("MemDiscountLimit", $cols)) {
            $limit = $cdModel->MemDiscountLimit();
        } else {
            $limit = 0;
        }
        $memType = $cdModel->memType();
        $mtModel = new MemtypeModel($dbc);
        $mtModel->memtype($memType);
        $mtModel->load();
        $memDesc = $mtModel->memDesc();

        $usc = ($cdModel->ChargeOk() == 1) ? ' CHECKED ' : '';
        /* Why?
         * How can ChargeOk be enabled if the form is hidden if it is disabled to begin with?
        $fsDisplay = ($cdModel->ChargeOk() == 1 && $limit == 0) ? 'none' : 'block';
         */
        $fsDisplay = 'block';

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
/* The interveneing ccredmemdiv prevents bootstrap's
    * gradient from appearing.
 */
.storechargepanelhead {
    background-color: #f5f5f5;
    border-bottom: 1px solid #ddd;
}
</style>";
        $ret .= "<div class='panel panel-default'>";

        $programMemberType = ($memNum == $this->programBankNumber)?"Program":"$memDesc";
        $ret .= "<div class=\"panel-heading storechargepanelhead\">Store Charge - {$programMemberType}"; //</div>";

        $ret .= " <a onclick=\"$('#_fhtt14042001').toggle();return false;\" href=''>" .
            "<img title='Let the Member make purchases on Credit (Run a tab) up to Limit " .
            "(Click for more)' src='{$FANNIE_URL}src/img/buttons/help16.png'>" .
            "</a>";
        $ret .= "</div><!-- /.panel-heading -->";
        $ret .= "<div class=\"panel-body\">";

        /* For bootstrapped v.1 retain the table coding.
         */
        // Maybe not with 100%
        $ret .= "<table class='MemFormTable' border='0'>";

        $mayNotUse = "";
        if (isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto') {
            if ($memNum < 99000 && $memType != 6) {
                $mayNotUse = "<tr><td>Regular Members may not use Store Charge</td></tr>";
            } elseif ($memNum > 99000) {
                global $FANNIE_PLUGIN_SETTINGS;
                if (is_array($FANNIE_PLUGIN_SETTINGS) &&
                    isset($FANNIE_PLUGIN_SETTINGS['CoopCredDatabase'])) {
                    $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['CoopCredDatabase']);
                    $ccmem = new CCredMembershipsModel($dbc);
                    $ccmem->cardNo($memNum);
                    foreach($ccmem->find() as $obj) {
                        $mayNotUse = "<tr><td>Co-op Cred Programs may not use Store Charge</td></tr>";
                        break;
                    }
                }
            }
        }
        if ($mayNotUse) {
            $ret .= $mayNotUse;
        } else {

            $ret .= "<tr>";
            $ret .= "<th title='Allow/prevent the member to use Store Charge, i.e. \"Run a tab\".'>Charge OK</th>";
            $ret .= sprintf('<td><input type="checkbox" name="use_s_c" %s />
                    </td>',$usc);

            $ret .= "<th>Current Balance</th>";
            $ret .= sprintf('<td>%.2f</td>',$infoW['balance']);	

            //$ret .= "<td><a href=\"{$FANNIE_URL}reports/AR/index.php?memNum=$memNum\">History</a></td></tr>";
            if ($memNum == $this->programBankNumber) {
                $today = date('Y-m-d');
                $reportLink = "<p style='margin:0em; font-family:Arial;line-height:1.0em;'>
                    <a href=\"{$FANNIE_URL}reports/StoreProgram/StoreProgramReport.php?date1=&amp;date2=&amp;card_no=0&amp;program=\"
                    title='List inputs to and payments from the program before today'
                    target='_store_events'
                    >Event History</a>
                    <br />
                    <a href=\"{$FANNIE_URL}reports/StoreProgram/StoreProgramReport.php?date1={$today}&amp;date2={$today}&amp;other_dates=on&amp;submit=Submit&amp;card_no=0&amp;program=\"
                    title='List inputs to and payments from the program today'
                    target='_store_events'
                    >Events Today</a>
                    </p>
                    ";
            } else {
                $td = (30*(24*(60*60)));
                $date1 = date('Y-m-d',(time()-$td));
                $reportLink = "<a
                    href=\"{$FANNIE_URL}reports/AR/index.php?memNum={$memNum}&amp;date1={$date1}\"
                    title='List payments and purchases for this member'
                    target='_blank'
                    ><p style='margin:0em; font-family:Arial;line-height:1.0em;'>Activity<br />Report</p></a>";
            }
            $ret .= ("<td>" . $reportLink);
            $ret .= "</td>";
            $ret .= "</tr>";

            $ret .= "<tr>";
            $ret .= "<th title='For Store Charge Limit must be greater than 0.'>Limit</th>";
            $ret .= sprintf('<td><input name="SC_limit" size="4" value="%d" />
                    </td>',$limit);

            $ret .= "<td colspan='1'><a href=\"{$FANNIE_URL}mem/correction_pages/MemArTransferTool.php?memIN=$memNum\">Transfer Store Charge</a></td>";
            //$ret .= "<td colspan='2'><a href=\"{$FANNIE_URL}mem/correction_pages/MemArEquitySwapTool.php?memIN=$memNum\">Convert Store Charge</a></td></tr>";

            // In this program only the Program Account may accept inputs.
            $args1="memIN=$memNum&amp;memEDIT=$memNum&amp;" .
                    "programNumber=$this->programNumber&amp;" .
                    "programBankNumber=$this->programBankNumber";
            $ret .= "<td colspan='2'>";
            if ($memNum == $this->programBankNumber) {
                $ret .= "| <a
                    href=\"{$FANNIE_URL}mem/correction_pages/StoreProgramInputTool.php?$args1\"
                    title='Input (deposit) external funds to the Program Account'
                    >Input Store Program</a>";
            } else {
                $ret .= "<a href=\"{$FANNIE_URL}mem/correction_pages/MemArEquitySwapTool.php?memIN=$memNum\">Convert Store Charge</a>";
            }
            $ret .= "</td>";
            $ret .= "</tr>";
        }


		$ret .= "</table>";
        $ret .= "</div><!-- /.panel-body -->";

        // Help chunk.
        $ret .= '<fieldset id="_fhtt14042001" style="display:none; width:440px;">' .
            "Let the Member make purchases on Credit ('Run a tab') up to Limit. ";
        if (isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto') {
            $ret .= "<br />If it is for an intra-store transfer ('Shrinkage') account " .
                "the Member Number must be higher than 99900 and the Limit very large (99999). ";
        }
        $ret .="<br />Un-ticking 'OK' will suspend the account when member data is refreshed on lanes. " .
            "<br />'Balance' shows how close the account is to 'Limit'.";
        $ret .= "</fieldset>";
        $ret .= sprintf("<input type='hidden' name='sc_orig_values' value='%d|%d' />",
                ($usc == '' ? 0 : 1), $limit);

// Bootstrap v.1 above here.

/*
        $ret = "<fieldset class='memTwoRow' style='display:{$fsDisplay};'><legend>Store Charge " .
            "- $programMemberType " .
            "<a onclick=\"$('#_fhtt14042001').toggle();return false;\" href=''>" .
            "<img title='Let the Member make purchases on Credit (Run a tab) up to Limit " .
            "(Click for more)' src='/IS4C/fannie/src/img/buttons/help16.png'>" .
            "</a>" .
            "</legend>";
        $ret .= "<table class=\"MemFormTable\" border=\"0\">";
*/


// Below here has been reformatted above.
/*
		$ret .= "</table></fieldset>";
        $ret .= '<fieldset id="_fhtt14042001" style="display:none; width:440px;">' .
            "Let the Member make purchases on Credit ('Run a tab') up to Limit. ";
        if (isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto') {
            $ret .= "<br />If it is for an intra-store transfer ('Shrinkage') account " .
                "the Member Number must be higher than 99900 and the Limit very large (99999). ";
        }
        $ret .="<br />Un-ticking 'OK' will suspend the account when member data is refreshed on lanes. " .
            "<br />'Balance' shows how close the account is to 'Limit'.";
        $ret .= "</fieldset>";
        $ret .= sprintf("<input type='hidden' name='sc_orig_values' value='%d|%d' />",
                ($usc == '' ? 0 : 1), $limit);
*/

		return $ret;
	}

    function SaveFormData($memNum, $json=array())
    {
		global $FANNIE_ROOT;
		$dbc = $this->db();
		if (!class_exists("CustdataModel")) {
			include($FANNIE_ROOT.'classlib2.0/data/models/CustdataModel.php');
        }

        $charge_ok = (FormLib::get_form_value('use_s_c','') == '') ? 0 : 1;
		$limit = FormLib::get_form_value('SC_limit',0);
        list($orig_ok, $orig_limit) =
            explode('|', FormLib::get_form_value('sc_orig_values',''));
        // Change only if changed in this sub-form.
        if ("{$charge_ok}|{$limit}" != FormLib::get_form_value('sc_orig_values','')) {
            if ($charge_ok && $limit == 0) {
                return '<p class="memMessage">**Problem in Store Charge: With Limit of 0 the Member cannot store-charge purchases.' .
                    '<br />Either do not tick OK or enter a higher Limit.</p>';
            }
            $cdModel = new CustdataModel($dbc);
            $cdModel->CardNo($memNum);
            $test = false;
            $cols = $cdModel->getColumns();
            // All the personNum's of this CardNo
            foreach($cdModel->find() as $obj) {
                $obj->ChargeOk($charge_ok);
                if (array_key_exists("ChargeLimit", $cols)) {
                    $obj->ChargeLimit($limit);
                } 
                if (array_key_exists("MemDiscountLimit", $cols)) {
                    $obj->MemDiscountLimit($limit);
                }
                $test = $obj->save();
            }

            if ($test === False) {
                return 'Error: Problem saving Store Charge limit<br />';
            } else {
                return '';
            }
        } else {
            return '';
        }
	}
}

?>
