<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op
    Copyright 2016 West End Food Co-op, Toronto, Canada

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
     */
    /*
     * For non-members only for
     *  - workshop purchases that include membership.
        - 19Dec2016 add department 1200 Fundraising
     * For members, only for:
     * - workshop purchases
            department 1300 MarketBucks, 1500 Workshops, 1600 CSO Sales, 2600 FarmDirect
        - 19Dec2016 add department 1200 Fundraising
      * - Coop Cred
      *   Programs that the member belongs to and to which they may inputs.
      * Cashback
      * - No
      * - En/Dis-abled: Lane Config > Extras > Tender Settings > Allow members to write checks over purchase amount
      * - Amount: Lane Config > Extras > Tender Settings > Check over limit
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

        $db = Database::pDataConnect();
        /* Get Coop Cred departments to which the member may input.
         */
        $coopCredDepartments = "";
        if (CoreLocal::get("CoopCredLaneDatabase") != "") {
            $CCLD = CoreLocal::get("CoopCredLaneDatabase");
            $member = CoreLocal::get("memberID");
            $cQuery = "SELECT p.paymentDepartment " .
                "FROM {$CCLD}.CCredPrograms AS p " .
                "INNER JOIN {$CCLD}.CCredMemberships AS m ON m.programID = p.programID " .
                "WHERE m.CardNo = ? AND p.active =1 " .
                "AND (p.inputOK =1 AND m.inputOK =1) " .
                "";
            $cStatement = $db->prepare($cQuery);
            $args = array();
            $args[] = $member;
            $cResults = $db->execute($cStatement, $args);
            while ($rslt = $db->fetch_row($cResults)) {
                $coopCredDepartments .= ", " . $rslt['paymentDepartment'];
            }
        }
        $TRANS = 'translog';
        $OP = 'opdata';
        $eQuery = "SELECT sum(total) AS chqtotal " .
            "FROM {$TRANS}.localtemptrans " .
            "WHERE trans_type = 'T' AND trans_subtype = 'CK' " .
            "AND trans_status NOT IN ('X','V','R') " .
            "";
        $eStatement = $db->prepare($eQuery);
        $args = array();
        $eResults = $db->execute($eStatement, $args);
        $previousChequesTendered = 0;
        if ($db->numRows($eResults) == 1) {
            $eRslt = $db->fetch_row($eResults);
            $previousChequesTendered = ($eRslt['chqtotal'] * -1);
        }

        $memberDepartments = '1100, 1200, 1150, 1300, 1500, 1600, 2100, 2600';
        $query = "SELECT t.department, " .
            "ROUND(SUM(t.total * (1 + CASE WHEN r.rate IS NOT NULL THEN r.rate ELSE 0 END)),2) as dtot " .
            "FROM {$TRANS}.localtemptrans t " .
            "LEFT JOIN {$TRANS}.taxrates r ON t.tax = r.id " .
            "WHERE t.department IN ({$memberDepartments}{$coopCredDepartments}) " .
            "AND t.trans_status NOT IN ('X','V','R') " .
            "GROUP BY t.department";
        $statement = $db->prepare($query);
        $args = array();
        $results = $db->execute($statement, $args);
        $nonMemberDepartments = array(1200,1500);
        $memberOkAmount = 0;
        $nonMemberOkAmount = 0;
        while ($rslt = $db->fetch_row($results)) {
            $memberOkAmount += $rslt['dtot'];
            if (in_array($rslt['department'],$nonMemberDepartments)) {
                $nonMemberOkAmount += $rslt['dtot'];
            }
        }

        if (CoreLocal::get("isMember") == 0) {
            if ($nonMemberOkAmount == 0) {
                $msg = _("Non-members may only write cheques for Workshop fees or Fundraising items.");
                return DisplayLib::xboxMsg(
                $msg,
                $clearButton);
            } elseif (($this->amount_tendered + $previousChequesTendered) > $nonMemberOkAmount) {
                $msg = _('Non-members may only write cheques for the exact amount of Workshop fees or Fundraising items: $') .
                    number_format($nonMemberOkAmount,2);
                return DisplayLib::xboxMsg(
                $msg,
                $clearButton);
            }
        } else {
            if (($this->amount_tendered + $previousChequesTendered) > $memberOkAmount) {
                $msg = _('Members may only write cheques for the exact amount of eligible purchases: $') .
                    number_format($memberOkAmount,2) .
                    "";
                return DisplayLib::xboxMsg(
                $msg,
                $clearButton);
            }
        }

        return true;
    }
    
    /**
      Set up state and redirect if needed
      @return True or a URL to redirect
     * In the standard module part of cheque endorsing is handled here.
    */
    public function preReqCheck()
    {

        /* Do the usual things
         * Was already done in the initial call of TenderModule, yes?
        parent::preReqCheck();
         * */

        /* Always confirm the cheque tender even if amount is exact.
         * Use of cheque is so rare that the assumption is that
         *  it is a mistake.
         * I don't want to prompt again if there was already a prompt for
         * the amount, but can't figure out how.
         *  The defaultPrompted test doesn't work, is always "".
            CoreLocal::get('defaultPrompted') != "Yes" &&
        */
        $this->amount_tendered = $this->amount;
        $this->amount_due = CoreLocal::get("amtdue");
        if (
            ($this->amount_tendered > 0 &&
            $this->amount_tendered <= $this->amount_due) &&
            CoreLocal::get("msgrepeat") == 0
        ) {
            CoreLocal::set("boxMsg",
                "<br />Confirm use of " . $this->name_string . " tender of " .
                sprintf('$%.2f', $this->amount_tendered)
            );
            CoreLocal::set('lastRepeat', 'confirmTenderType');
            CoreLocal::set('boxMsgButtons', array(
                'Confirm [enter]' => '$(\'#reginput\').val(\'\');submitWrapper();',
                'Cancel [clear]' => '$(\'#reginput\').val(\'CL\');submitWrapper();',
            ));
            return MiscLib::base_url().'gui-modules/boxMsg2.php';
        } else if (CoreLocal::get('msgrepeat') == 1 &&
            CoreLocal::get('lastRepeat') == 'confirmTenderType') {
            CoreLocal::set('msgrepeat', 0);
            CoreLocal::set('lastRepeat', '');
        }

        return true;
    }

    /* 
      Prompt for the cashier when no total is provided
      @return string URL
      Typically this sets up session variables and returns
      the URL for boxMsg2.php.
     * The standard handler has some cheque endorsement and equity handling.
     */
    public function defaultPrompt()
    {
        /* What I'm trying to do with defaultPrompted doesn't work.
         * Why doesn't it persist?
        CoreLocal::set('defaultPrompted', "Yes");
         */
        return parent::defaultPrompt();
    }

    /**
      What description should be used for change records associated with this tender
      @return string change description
    */
    public function changeMsg()
    {
        //return $this->change_string;
        return "Cash Back";
    }

}

