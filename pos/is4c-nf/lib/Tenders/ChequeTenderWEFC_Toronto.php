<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

    This file is part of IT CORE.

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

/**
  @class ChequeTenderWEFC_Toronto  
  Tender module for cheques
*/

class ChequeTenderWEFC_Toronto extends TenderModule 
{
    /* TODO
     * Bug: will accept a 2nd cheque for an amount <= $eligible.
     *  Needs to find or store cheques-already-written and subtract from $eligible.
     */
    /*
      * Only for members or for workshop purchases that include membership.
      * For members, only for:
      * - workshop purchases
            department 1300 MarketBucks, 1500 Workshops, 1600 CSO Sales, 2600 FarmDirect
      * Cashback
      * - No
      * - En/Dis-abled: Lane Config > Extras > Tender Settings > Allow members to write checks over purchase amount
      * - Amount: Lane Config > Extras > Tender Settings > Check over limit
      * Might want to re-define defaultTotal (i.e. amount tendered) to $eligibleAmount
      *  as defined below.
     */

    protected $amount_tendered;
    protected $amount_due;

    public function ChequeTenderWEFC_Toronto($code, $amt)
    {
        parent::__construct($code, $amt);
        $this->amount_tendered = $this->amount;
        $this->amount_due = CoreLocal::get("amtdue");
    }

    /**
      Check for errors
      @return True or an error message string
    */
    public function errorCheck()
    {
        // Does clear mean override?
        $clearButton = array('OK [clear]' => 'parseWrapper(\'CL\');');

        /* Get Coop Cred departments FROM coop_cred_lane.CCredPrograms AS p
         * .paymentDepartment
         * JOIN CCredMemberships AS m ON programID ...
         * WHERE m.CardNo = $member p.active =1 and p.inputOK =1
         * AND 
         */
        $db = Database::pDataConnect();
        //$OP = CoreLocal::get("opDatabase");
        //$TRANS = CoreLocal::get("transDatabase");
        $TRANS = 'translog';
        $OP = 'opdata';
        $query = "SELECT t.department, " .
            "ROUND(SUM(t.total * (1 + CASE WHEN r.rate IS NOT NULL THEN r.rate ELSE 0 END)),2) as dtot " .
            "FROM {$TRANS}.localtemptrans t " .
            "LEFT JOIN {$TRANS}.taxrates r ON t.tax = r.id " .
            "WHERE (t.department IN (1100, 1150, 1300, 1500, 1600, 2100, 2600) " .
            "OR t.department BETWEEN 1021 AND 1049) " .
            "AND t.trans_status NOT IN ('X','V','R') " .
            "GROUP BY t.department";
        $statement = $db->prepare_statement($query);
        $args = array();
        $results = $db->exec_statement($statement, $args);
        $eligibleAmount = 0;
        $workshopDepartment = 1500;
        $workshopAmount = 0;
        while ($rslt = $db->fetch_row($results)) {
            $eligibleAmount += $rslt['dtot'];
            if ($rslt['department'] == $workshopDepartment) {
                $workshopAmount += $rslt['dtot'];
            }
        }

        if (CoreLocal::get("isMember") == 0) {
            if ($workshopAmount == 0) {
                $msg = _("Non-members may only write cheques for Workshop fees.");
                return DisplayLib::boxMsg(
                $msg,
                '',
                false,
                $clearButton);
            } elseif ($this->amount_tendered > $workshopAmount) {
                $msg = _('Non-members may only write cheques for the exact amount of Workshop fees: $') .
                    number_format($workshopAmount,2);
                return DisplayLib::boxMsg(
                $msg,
                '',
                false,
                $clearButton);
            }
        } else {
            if ($this->amount_tendered > $eligibleAmount) {
                $msg = _('Members may only write cheques for the exact amount of eligible purchases: $') .
                    number_format($eligibleAmount,2);
                return DisplayLib::boxMsg(
                $msg,
                '',
                false,
                $clearButton);
            }
        }

        /* -------------
        if ( CoreLocal::get("isMember") != 0 &&
            (($this->amount_tendered - CoreLocal::get("amtdue") - 0.005) > CoreLocal::get("dollarOver")) &&
            (CoreLocal::get("cashOverLimit") == 1))
        {
            return DisplayLib::boxMsg(
                _("member check tender cannot exceed total purchase by over $") . CoreLocal::get("dollarOver"),
                '',
                false,
                $clearButton
            );
        } elseif (CoreLocal::get("isMember") == 0) {
            $msg = _('Non-members may not write cheques.');
            return DisplayLib::xboxMsg($msg, $clearButton);
        } elseif (CoreLocal::get("isMember") == 0  &&
            ($this->amount_tendered - CoreLocal::get("amtdue") - 0.005) > 0)
        { 
            $msg = _('Non-members may not write cheques for more than the total purchase.');
            return DisplayLib::xboxMsg($msg, $clearButton);
        }
         */

        /* Elibigle purchase woodshed.
        SELECT t.upc, t.description, t.tax, t.total, r.rate, 
            SUM(t.total * (1 + CASE WHEN r.rate IS NOT NULL THEN r.rate ELSE 0 END)) as tot,
            ROUND(SUM(t.total * (1 + CASE WHEN r.rate IS NOT NULL THEN r.rate ELSE 0 END)),2) as tot2
            FROM `dtransactions` t
            LEFT JOIN core_op.taxrates r on t.tax = r.id
            WHERE (t.department IN (1100, 1150, 1300, 1500, 1600, 2100, 2600)
            OR t.department BETWEEN 1021 AND 1049)
            AND t.trans_status NOT IN ('X','V','R')

        */
        /*
            SUM(t.total * (1 + CASE WHEN r.rate IS NOT NULL THEN r.rate ELSE 0 END)) as tot
            SELECT t.upc, t.description, t.tax, t.total, r.rate, 
            (t.total * (1 + CASE WHEN r.rate IS NOT NULL THEN r.rate ELSE 0 END)) as tot
            FROM `dtransactions` t
            LEFT JOIN core_op.taxrates r on t.tax = r.id
            WHERE 1
            AND t.tax IN (1,2)
            WHERE t.department IN (1100, 1150, 1300, 1500, 1600, 2100, 2600)
            OR t.department BETWEEN 1021 AND 1049);
         */

        return true;
    }
    
    /**
      Set up state and redirect if needed
      @return True or a URL to redirect
     * For WEFC_Toronto I assume the parent's function will be used if nothing
     * is defined here.
     * Since WEFC_Toronto does not use franking this will never do anything
     * special.
     * If it needs to be here it should just return True.
    public function preReqCheck()
    {
        if (CoreLocal::get("enableFranking") != 1) {
            return true;
        }

        // check endorsing
        if (CoreLocal::get("msgrepeat") == 0) {
            return $this->DefaultPrompt();
        }

        return true;
    }
    */

    /* For WEFC_Toronto I assume the parent's function will be used if nothing
     * is defined here.
     * Since WEFC_Toronto does not use franking this will never do anything
     * special.
     * If it needs to be here it should just return True.
    public function defaultPrompt()
    {
        if (CoreLocal::get("enableFranking") != 1) {
            return parent::defaultPrompt();
        }

        CoreLocal::set('RepeatAgain', false);

        $ref = trim(CoreLocal::get("CashierNo"))."-"
            .trim(CoreLocal::get("laneno"))."-"
            .trim(CoreLocal::get("transno"));

        if ($this->amount_tendered === False) {
            $this->amount_tendered = $this->defaultTotal();
        }

        $msg = "<br />"._("insert")." ".$this->name_string.
            ' for $'.sprintf('%.2f',$this->amount_tendered) . '<br />';
        if (CoreLocal::get("LastEquityReference") == $ref) {
            $msg .= "<div style=\"background:#993300;color:#ffffff;
                margin:3px;padding: 3px;\">
                There was an equity sale on this transaction. Did it get
                endorsed yet?</div>";
        }

        CoreLocal::set("boxMsg",$msg);
        CoreLocal::set('strEntered', (100*$this->amount_tendered).$this->tender_code);
        CoreLocal::set('boxMsgButtons', array(
            'Endorse [enter]' => '$(\'#reginput\').val(\'\');submitWrapper();',
            'Cancel [clear]' => '$(\'#reginput\').val(\'CL\');submitWrapper();',
        ));

        return MiscLib::base_url().'gui-modules/boxMsg2.php?endorse=check&endorseAmt='.$this->amount_tendered;
    }
    */

}

