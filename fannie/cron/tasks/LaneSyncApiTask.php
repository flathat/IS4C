<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op
    Copyright 2014 West End Food Co-op

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

/* TODO
 * How to pass in the parameter $oneLane
 *  For CLI, FannieTask could pass 3rd+ args to run().
 */

class LaneSyncApiTask extends FannieTask
{
    public $name = 'Nightly Lane Sync using API';

public $description = "Bring lane tables into the same state as server tables.

Retrieve to the server from the following lane tables:
   valutecRequest, valutecRequestMod, valutecResponse
Replace the following lane tables with contents of
  the server table:
   products, custdata, memberCards, employees, departments,
   custReceiptMessage
Optionally also replace:
   productUser

If you can use fannie/sync/special/generic.mysql.php
  the transfers will go much faster.

Coordinate this with cronjobs such as nightly.batch.php
  that update the tables this is pushing to the lanes
  so that the lanes have the most current data.

By default runs on all lanes.
If run from the command line can take a lane-number
  parameter: LaneSyncApiTask lane#
  to sync a single lane.

Replacement for nightly.lanesync.php using Fannie's API
  instead of cURL.
";

    public $default_schedule = array(
        'min' => 30,
        'hour' => 0,
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
    );

    /* When $FannieTask->arguments exists:
    public function run($lane=array(0))
     */
    public function run()
    {
        global $FANNIE_LANES, $FANNIE_COMPOSE_LONG_PRODUCT_DESCRIPTION,
                $FANNIE_COOP_ID;

        /* When $FannieTask->arguments exists:
         * $oneLane = (isset($this->arguments[0])) ? $this->arguments[0] : 0;
         * if (!preg_match('/^\d+$/',$oneLane)) {
         * $this->cronMsg("lane argument <{$oneLane}> must be an integer.");
         * return;
         * }
         */
        $oneLane = (isset($this->arguments[0])) ? $this->arguments[0] : 0;
        if ($oneLane > count($FANNIE_LANES)) {
            echo $this->cronMsg("oneLane: $oneLane is more than the ".
                count($FANNIE_LANES)." that are configured.");
            return;
        }

        $regularPullTables = array(
            'valutecRequest',
            'valutecRequestMod',
            'valutecResponse'
        );
        foreach ($regularPullTables as $table) {
            $result = SyncLanes::pullTable("$table", 'trans',
                SyncLanes::TRUNCATE_SOURCE,
                $oneLane);
            echo $this->cronMsg($result['messages']);
        }

        $regularPushTables = array(
            'products',
            'custdata',
            'memberCards',
            'custReceiptMessage',
            'employees',
            'departments',
            'houseCoupons',
            'houseCouponItems',
            'houseVirtualCoupons'
        );
        foreach ($regularPushTables as $table) {
            $result = SyncLanes::pushTable("$table", 'op',
                SyncLanes::TRUNCATE_DESTINATION,
                $oneLane);
            echo $this->cronMsg($result['messages']);
        }

        if ( isset($FANNIE_COMPOSE_LONG_PRODUCT_DESCRIPTION) &&
                $FANNIE_COMPOSE_LONG_PRODUCT_DESCRIPTION == True ) {
            $result = SyncLanes::pushTable('productUser', 'op',
                SyncLanes::TRUNCATE_DESTINATION,
                $oneLane);
            echo $this->cronMsg($result['messages']);
        }

        if ( isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto' ) {
            $wefcPushTables = array(
                'tenders',
                'memtype',
                'meminfo'
            );
            foreach ($wefcPushTables as $table) {
                $result = SyncLanes::pushTable("$table", 'op',
                    SyncLanes::TRUNCATE_DESTINATION,
                    $oneLane);
                echo $this->cronMsg($result['messages']);
            }
        }

        echo $this->cronMsg(basename(__FILE__) ." done.");

    }
}
