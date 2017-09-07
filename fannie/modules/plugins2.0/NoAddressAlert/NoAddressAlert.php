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
/* TODO
 * Can $FANNIE_OP_DB be the default?
 * Can the table be dropped upon disablement?
 */

include(dirname(__FILE__) . '/../../../config.php');
if (!class_exists('FannieAPI')) {
	include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class NoAddressAlert extends FanniePlugin
{

	/**
	  Desired settings. These are automatically exposed
	  on the 'Plugins' area of the install page and,
	  upon enablement of the plugin, written to ini.php
		and from then on maintained there.
	*/
	public $plugin_settings = array(
		'NoAddressAlertDatabase' => array(
			'label'=>'Database',
			'default'=>'core_op',
			'description'=>'Database to store a temporary table for
					setting aside custdata.LastChange.
                    <br />The default CORE database core_op is probably a good choice,
                   but it can be a separate one.
                    <br />The name of a new database should be all lower-case.'
		)
		, 'NoAddressAlertMessage' => array(
			'label'=>'Message',
			'default'=>'Need mailing address!',
			'description'=>'The message that will be displayed on the POS screen
            when the member is identified.
            <br />25 characters or less.'
		)
	);

	public $plugin_description =
		'Plugin to put a message in custdata.blueLine
        for members with patronage for whom an address
            is lacking.
            <br />The message is displayed on the POS terminal.
            <br />Implemented as a the Scheduled Task \'Patronage No Address Alert\'.
            <br />(Be sure to enable and schedule it.)';

	public function settingChange()
	{
		global $FANNIE_ROOT, $FANNIE_PLUGIN_SETTINGS;

        /* empty/absent if the plugin isn't enabled. */
        if (empty($FANNIE_PLUGIN_SETTINGS['NoAddressAlertDatabase'])) {
            return;
        }
		$db_name = $FANNIE_PLUGIN_SETTINGS['NoAddressAlertDatabase'];

        /* May want to support this.
        $dropAllTables =
            (array_key_exists('NoAddressAlertdropTable',$FANNIE_PLUGIN_SETTINGS)) ?
                $FANNIE_PLUGIN_SETTINGS['NoAddressAlertdropTable'] : False;
         */

        /* Check for problems in settings.
         */
        if ($FANNIE_PLUGIN_SETTINGS['NoAddressAlertMessage'] == '') {
            $msg="***ERROR: The NoAddressAlert message is empty.";
            echo "<br />$msg";
            $dbc->logger($msg);
            return;
        }
        $maxMsgLength = 25;
        if (strlen($FANNIE_PLUGIN_SETTINGS['NoAddressAlertMessage']) > $maxMsgLength) {
            $msg= sprintf("***ERROR: The NoAddressAlert message is %d characters too long.",
                strlen($FANNIE_PLUGIN_SETTINGS['NoAddressAlertMessage']) - $maxMsgLength);
            echo "<br />$msg";
            $dbc->logger($msg);
            return;
        }

		// Creates the database if it doesn't already exist.
		$dbc = FannieDB::get($db_name);
		
        /* The tables named in the models will be created
         *  if they don't exist
         *  but will not be touched if they do,
         *   neither be re-created or modified.
         */
		$models = array(
			'TempLastChangeModel'
		);
        // Would this help with dropping?
		//$models = array( array('name' => 'TempLastChangeModel', 'drop' => True));

		foreach($models as $model_class){
            $filename = dirname(__FILE__).$model_class.'.php';
			if (!class_exists($model_class)) {
				include_once($filename);
            }
			$instance = new $model_class($dbc);
            $table = $instance->name();
            if ($dbc->tableExists($table)) {
				$msg="Table $table named in {$model_class} already exists. No change.";
                $news[] = $msg;
				$dbc->logger("$msg");
                continue;
            }
			$try = $instance->create();		
			if ($try) {
				$msg="Created table $table as specified in {$model_class}";
				$dbc->logger("$msg");
			} else {
				$msg="Failed to create table specified in {$model_class}";
				$dbc->logger("$msg");
			}
            /* Generate the accessor function code for each column.
             * The Model file must be writable by the webserver user.
			*/
            if (is_writable($filename)) {
                $try = $instance->generate($filename);
                //$try = $instance->generate(dirname(__FILE__).'/models/'.$model_class.'.php');
                if ($try) {
                    //echo "Generated $model_class accessor functions\n";
                    $dbc->logger("[Re-]Generated $model_class accessor functions.");
                } else {
                    //echo "Failed to generate $model_class functions\n";
                    $dbc->logger("Failed to [re-]generate $model_class accessor functions.");
                }
            } else {
                $dbc->logger("Could not [re-]generate $model_class accessor functions " .
                    "because the model-file is not writable by the webserver user.");
            }
        // tables
		}

	// settingChange()
	}

    /**
        Callback. Triggered when plugin is disabled
        Possibly drop the table.
        Possibly drop the db if it is not a standard one
         and is empty after the table is dropped.
    public function pluginDisable()
    {

    }
    */

// class
}

?>
