<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
    Copyright 2015 River Valley Market, Northampton, MA

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

class NoAddressAlertTask extends FannieTask
{
    public $name = 'Patronage No Address Alert';
    public $pluginName = 'NoAddressAlert';

    /* Keep lines to 60 chars for the cron manager popup window.
     *                             --------------  60 edge-v */
    public $description = '
- For members without an address assigns a
  message to custdata.blueLine that will
  appear on the POS screen.

Should be run once a day, before custdata
is copied (synced) to lanes.
';    

    public $default_schedule = array(
        'min' => 0,
        'hour' => 0,
        'day' => 1,
        'month' => 1,
        'weekday' => '*',
    );

    public function __construct() {
        $this->description = $this->name . "\n" . $this->description;
        //parent::__construct();
    }

    public function run()
    {
        global $FANNIE_LANES;
        global $FANNIE_PLUGIN_LIST;
        global $FANNIE_PLUGIN_SETTINGS;
        if (!FanniePlugin::isEnabled($this->pluginName)) {
            echo $this->cronMsg("Plugin '{$this->pluginName}' is not enabled.");
            return False;
        }
        if (
            !array_key_exists("{$this->pluginName}Database", $FANNIE_PLUGIN_SETTINGS) ||
            empty($FANNIE_PLUGIN_SETTINGS["{$this->pluginName}Database"])
        ) {
            echo $this->cronMsg("Setting: '{$this->pluginName}Database' is not set.");
            return False;
        }
        $server_db = $FANNIE_PLUGIN_SETTINGS["{$this->pluginName}Database"];

        $dbc = FannieDB::get($server_db);
        if ($dbc === False) {
            echo $this->cronMsg("Unable to connect to {$server_db}.");
            return False;
        }
        $tempTable = 'TempLastChange';
        if (!$dbc->tableExists("$tempTable")) {
            echo $this->cronMsg("{$server_db}.{$tempTable} doesn't exist.");
            return False;
        }

        // Set aside the current custdata.LastChange.
        $query = "TRUNCATE table $tempTable";
        $rslt = $dbc->query($query);
        if ($rslt === False) {
            echo $this->cronMsg("Failed: $query");
            return False;
        }
$rnge = "526 AND 550";
        $query = "INSERT INTO $tempTable SELECT CardNo, blueLine, LastChange from custdata";
        $rslt = $dbc->query($query);
        if ($rslt === False) {
            echo $this->cronMsg("Failed: $query");
            return False;
        }

        /* Reset blueLine to it's normal value: CardNo - LastName
         * Would it be better to have stored that in $tempTable and get it
         *  from there in case it is non-standard?
         */
        $query = "UPDATE custdata SET blueLine =
            SUBSTR(CONCAT(CAST(CardNo AS CHAR), ' - ', LastName),1,50)";
        $rslt = $dbc->query($query);
        if ($rslt === False) {
            echo $this->cronMsg("Failed: $query");
            return False;
        }

        /* Set blueLine to the NoAddressAlert if street or zip are lacking
         * The message may be up to 25 chars; blueLine is 50
         */
        $blueLine = $FANNIE_PLUGIN_SETTINGS["{$this->pluginName}Message"];
        if ($blueLine == '') {
            echo $this->cronMsg("Problem: \$blueLine is empty.");
            return False;
        }
        $query = "UPDATE custdata c
            LEFT JOIN meminfo m ON c.CardNO = m.card_no
            SET c.blueLine = CONCAT(CAST(CardNo AS CHAR), ' - ',
                SUBSTR(LastName,1,14),' - ','{$blueLine}')
            WHERE c.Type ='PC' AND c.personnum =1
            AND (coalesce(m.street, '' ) = '' OR coalesce(m.zip, '' ) = '')";
        $rslt = $dbc->query($query);
        if ($rslt === False) {
            echo $this->cronMsg("Failed: $query");
            return False;
        }

        // Restore LastChange
        $query = "UPDATE custdata c
            JOIN TempLastChange t ON c.CardNo = t.CardNo
            SET c.LastChange = t.LastChange";
        $rslt = $dbc->query($query);
        if ($rslt === False) {
            echo $this->cronMsg("Failed: $query");
            return False;
        }

        /* Debug
         echo $this->cronMsg("All OK.");
         */

    // /run
    }

// /class
}
