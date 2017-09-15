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

use COREPOS\pos\lib\Tenders\StoreChargeTender;

/**
  @class StoreChargeTender
  Tender module for charge accounts
*/
/** WEFC:
 * It is clumsy. In part because I cannot get the functions to work the way
 *  I understan they're supposed to.
*/
class StoreChargeTenderWEFC_Toronto extends StoreChargeTender 
{

    /**
      Check for errors
      @return True or an error message string
     */
     public function errorCheck()
    {
        return parent::errorCheck();

        /* I think some of the formatting is ugly and don't understand why
         * xboxMsg is not used for all of these since they are all errors,
         * but will stick with the standard version for now since I'm making
         * no substantial change.

        $charge_ok = PrehLib::chargeOk();
    
        $buttons = array('[clear]' => 'parseWrapper(\'CL\');');
        if ($charge_ok == 0) {
            return DisplayLib::boxMsg(
                _("member") . ' ' . CoreLocal::get("memberID") . '<br />' .
                _("is not authorized") . '<br />' ._("to make charges"),
                'Not Allowed',
                false,
                $buttons
            );
        } else if (CoreLocal::get("availBal") < 0) {
            return DisplayLib::boxMsg(
                _("member") . ' ' . CoreLocal::get("memberID") . '<br />' .
                _("is over limit"),
                'Over Limit',
                false,
                $buttons
            );
        } elseif ((abs(CoreLocal::get("memChargeTotal"))+ $this->amount) >= (CoreLocal::get("availBal") + 0.005)) {
            $memChargeRemain = CoreLocal::get("availBal");
            $memChargeCommitted = $memChargeRemain + CoreLocal::get("memChargeTotal");
            return DisplayLib::xboxMsg(
                _("available balance for charge") . '<br />' .
                _("is only \$") . $memChargeCommitted,
                $buttons
            );
        } elseif (abs(MiscLib::truncate2(CoreLocal::get("amtdue"))) < abs(MiscLib::truncate2($this->amount))) {
            return DisplayLib::xboxMsg(
                _("charge tender exceeds purchase amount"),
                $buttons
            );
        }

        return true;

    */
    }

    /**
      Allow the tender to be used without specifying a total
      @return boolean, default is true
      * Setting to false or true makes no difference to behaviour.
      * Why id disabledPrompt() not called if it false?
      * See $PCLP/DefaultTender.php
      * PHP function names are supposed to be case insensitive.
      *  DefaultTender.php uses initial caps but TenderModule and
      *   its children do not.
    public function AllowDefault()
    {
        return true;
    }
    */

    /**
      Error message shown if tender cannot be used without
      specifying a total
      @return html string
    public function DisabledPrompt()
    {
        $clearButton = array('OK [clear]' => 'parseWrapper(\'CL\');');
        return DisplayLib::boxMsg(
            _('Amount required for ') . $this->name_string,
            '',
            false,
            $clearButton
        );
    }
    */

    /* Work in Progress
     * autoconfirm value 1/0 doesn't seem to make any difference default=1
     * Testing this "later" doesn't work, so there must be something I don't
     *  understand about the sequence of operation.
            CoreLocal::set('defaultPrompted',"yes");
            parent::defaultPrompt();
     * If this function isn't specified it does TenderModule::defaultPrompt(),
     *  not parent::defaultPrompt(). Why is that? Misunderstanding?
     *  It does TenderModule::defaultPrompt() even with parent::defaultPrompt().
     *   What am I missing?
     * Would it be better for $allowDefault = false? It seems error prone to
     *  require typing the amount.
     */
    public function defaultPrompt()
    {
        return parent::defaultPrompt();
        /*
        $amt = $this->DefaultTotal();
        CoreLocal::set('strEntered', (100*$amt).$this->tender_code);
        return MiscLib::base_url().'gui-modules/boxMsg2.php?autoconfirm=1';
        */
    }
    
    /**
      Set up state and redirect if needed
      @return True or a URL to redirect
    */
    public function preReqCheck()
    {
        parent::preReqCheck();

        /* WEFC_Toronto addition.
         * Always confirm the tender even if amount is exact.
         * I don't want to prompt again if there was already a prompt for
         * the amount, but can't figure out how.
         *  The defaultPrompted test doesn't work, is always "".
            CoreLocal::get('defaultPrompted') != "yes" &&
         * At the moment I've lost track of what this actually does.
        */
        $this->amount_tendered = $this->amount;
        $this->amount_due = CoreLocal::get("amtdue");
        if (
            ($this->amount_tendered > 0 &&
            $this->amount_tendered <= $this->amount_due) &&
            CoreLocal::get("msgrepeat") == 0
        ) {
            CoreLocal::set("boxMsg",
                "<br />Confirm " . $this->name_string . " tender of " .
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
}

