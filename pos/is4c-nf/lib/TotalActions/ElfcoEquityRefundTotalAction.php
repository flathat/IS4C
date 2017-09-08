<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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
  @class ElfcoEquityRefundTotalAction
  Tender the default amount of MI for eligible members.
*/
class ElfcoEquityRefundTotalAction extends TotalAction
{
    /**
      Apply action
      @return [boolean] true if the action
        completes successfully (or is not
        necessary at all) or [string] url
        to redirect to another page for
        further decisions/input.
    */
    public function apply()
    {
        global $CORE_LOCAL;
        /*
         */
        /*
        $db = Database::pDataConnect();
        $repeat = CoreLocal::get('msgrepeat');

        CoreLocal::set('msgrepeat', $repeat);
         */

        /*trivial:
        return true;
         */
        $repeat = CoreLocal::get('msgrepeat');
        $strEntered = CoreLocal::get('strEntered');

        /* In addition to returning whether the member may
         *  StoreCharge (EquityRefund) sets or gets $CORE_LOCAL[]:
         * 'memChargeTotal' is the amount of MI already used in the transaction
         * 'balance' is custdata.Balance
         * 'availBal' is (custdata.ChargeLimit - custdata.Balance) + memChargeTotal
         *   as string: 1234.56 (no comma)
         * which are then available to the class.
         */
        $chargeOk = PrehLib::chargeOk();
        /* Is allowed, but also for whether member has any Balance,
         *  which is > 0 in this pay-forward scenario
         *  because: (c.ChargeLimit - c.Balance AS availBal)
         *    ChargeLimit == 0, Balance < 0, so (0 - -1) = +1
         * Let the tender adjust the default for the actual amount available.
         *
         * Why is this here again? Tired?
            if (CoreLocal::get('chargeOk') == 0 &&
            if ($chargeOk && CoreLocal::get('availBal') < 0) {
                }
            if ($chargeOk) {
            }
         */
        if ($chargeOk && CoreLocal::get('availBal') > 0) {
            if (strpos($strEntered,'MI') === False) {
                if ($repeat) {
                    /* Will this keep it from looping? No.
                    CoreLocal::set("strEntered","TL");
                     * */
                    return true;
                } else {
                    /* strEntered is TL at this point. */
                    CoreLocal::set("boxMsg","Member may use Equity Refund. strEntered:" . $strEntered);
                    /* quiet=1 means no-beep
                     * autoconfirm=1 means don't display the message ? and assume Confirm
                     */
                    /* In 1.8 there are no default buttons.
                     */
                    CoreLocal::set('boxMsgButtons', array(
                        'Confirm [enter]' => '$(\'#reginput\').val(\'\');submitWrapper();',
                        'Cancel [clear]' => '$(\'#reginput\').val(\'CL\');submitWrapper();',
                    ));
                    CoreLocal::set("strEntered","MI");
                    /* What does boxMsg2.php accomplish if it doesn't display anything
                     * or ask for any decision?
                     */
                    //return MiscLib::baseURL()."gui-modules/boxMsg2.php?quiet=1";
                    return MiscLib::baseURL()."gui-modules/boxMsg2.php?autoconfirm=1";
                }
            } else {
                CoreLocal::set("strEntered","TL");
            }
        }

        return true;

    // apply
    }

// class
}
