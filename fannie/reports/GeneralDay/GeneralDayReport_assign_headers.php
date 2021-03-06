<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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
/* Why does ReportsIndexPage list this as "Base Class" ?
 */

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class GeneralDayReport_assign_headers extends FannieReportPage
{

	function preprocess(){
        //? parent::preprocess();

		global $FANNIE_WINDOW_DRESSING;
        $this->report_set = 'Sales Reports';
		$this->title = "Fannie : General Day Report AH";
		$this->header = "General Day Report AH";
		$this->report_cache = 'none';
		$this->grandTTL = 1;
		$this->multi_report_mode = True;
		$this->sortable = False;

		if (isset($_REQUEST['date1'])){
			$this->content_function = "report_content";

			if ( isset($FANNIE_WINDOW_DRESSING) )
				$this->has_menus($FANNIE_WINDOW_DRESSING);
			else
				$this->has_menus(False);
			$this->report_headers = array('Desc','Qty','Amount');

			/**
			  Check if a non-html format has been requested
			*/
			if (isset($_REQUEST['excel']) && $_REQUEST['excel'] == 'xls') {
				$this->report_format = 'xls';
				$this->has_menus(False);
            } elseif (isset($_REQUEST['excel']) && $_REQUEST['excel'] == 'csv') {
				$this->report_format = 'csv';
				$this->has_menus(False);
            }
		}
		else 
			$this->add_script("../../src/CalendarControl.js");

		return True;
	}

	function report_description_content() {
		return(array('<p></p>'));
		//return(array('&nbsp;'));
	}

	function fetch_report_data(){
		global $dbc, $FANNIE_ARCHIVE_DB, $FANNIE_EQUITY_DEPARTMENTS, $FANNIE_OP_DB,
			$FANNIE_COOP_ID;
		$d1 = FormLib::get_form_value('date1',date('Y-m-d'));
		$dates = array($d1.' 00:00:00',$d1.' 23:59:59');
		$data = array();

		if ( isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto' )
			$shrinkageUsers = " AND (d.card_no NOT BETWEEN 99900 AND 99998)";
		else
			$shrinkageUsers = "";

		$dlog = select_dlog($d1);
		$tenderQ = $dbc->prepare("SELECT 
			TenderName,count(d.total),sum(d.total) as total
			FROM $dlog as d,
				{$FANNIE_OP_DB}.tenders as t 
			WHERE d.tdate BETWEEN ? AND ?
				AND d.trans_subtype = t.TenderCode
				AND d.total <> 0{$shrinkageUsers}
			GROUP BY t.TenderName ORDER BY TenderName");
		$tenderR = $dbc->execute($tenderQ,$dates);
		$report = array();
		while($tenderW = $dbc->fetchRow($tenderR)){
			$record = array($tenderW['TenderName'],$tenderW[1],
					sprintf('%.2f',$tenderW['total']));
			$report[] = $record;
		}
		$data[] = $report;

		$salesQ = $dbc->prepare("SELECT m.super_name,sum(d.quantity) as qty,
				sum(d.total) as total
				FROM $dlog AS d LEFT JOIN
				{$FANNIE_OP_DB}.MasterSuperDepts AS m ON d.department=m.dept_ID
				WHERE d.tdate BETWEEN ? AND ?
					AND d.department <> 0 AND d.trans_type <> 'T'{$shrinkageUsers}
				GROUP BY m.super_name ORDER BY m.super_name");
		$salesR = $dbc->execute($salesQ,$dates);
		$report = array();
		while($salesW = $dbc->fetchRow($salesR)){
			$record = array($salesW['super_name'],
					sprintf('%.2f',$salesW['qty']),
					sprintf('%.2f',$salesW['total']));
			$report[] = $record;
		}
		$data[] = $report;

		$discQ = $dbc->prepare("SELECT m.memDesc, SUM(d.total) AS Discount,count(*)
				FROM $dlog d
				INNER JOIN {$FANNIE_OP_DB}.custdata c ON d.card_no = c.CardNo AND c.personNum=1
				INNER JOIN {$FANNIE_OP_DB}.memtype m ON c.memType = m.memtype
				WHERE d.tdate BETWEEN ? AND ?
			       AND d.upc = 'DISCOUNT'{$shrinkageUsers}
				AND total <> 0
				GROUP BY m.memDesc ORDER BY m.memDesc");
		$discR = $dbc->execute($discQ,$dates);
		$report = array();
		while($discW = $dbc->fetchRow($discR)){
			$record = array($discW['memDesc'],$discW[2],$discW[1]);
			$report[] = $record;
		}
		$data[] = $report;

		$taxSumQ = $dbc->prepare("SELECT  sum(total) as tax_collected
			FROM $dlog as d 
			WHERE d.tdate BETWEEN ? AND ?
				AND (d.upc = 'tax'){$shrinkageUsers}
			GROUP BY d.upc");
		$taxR = $dbc->execute($taxSumQ,$dates);
		$report = array();
		while($taxW = $dbc->fetchRow($taxR)){
			$record = array('Sales Tax',null,round($taxW['tax_collected'],2));
			$report[] = $record;
		}
		$data[] = $report;

		$transQ = $dbc->prepare("select q.trans_num,sum(q.quantity) as items,transaction_type, sum(q.total) from
			(
			SELECT trans_num,card_no,quantity,total,
			m.memdesc as transaction_type
			FROM $dlog as d
				LEFT JOIN {$FANNIE_OP_DB}.custdata as c on d.card_no = c.cardno
				LEFT JOIN {$FANNIE_OP_DB}.memtype as m on c.memtype = m.memtype
			WHERE d.tdate BETWEEN ? AND ?
				AND trans_type in ('I','D')
				AND upc <> 'RRR'{$shrinkageUsers}
				AND c.personNum=1
			) as q 
			group by q.trans_num,q.transaction_type");
		$transR = $dbc->execute($transQ,$dates);
		$trans_info = array();
		while($row = $dbc->fetchArray($transR)){
			if (!isset($transinfo[$row[2]]))
				$transinfo[$row[2]] = array(0,0.0,0.0,0.0,0.0);
			$transinfo[$row[2]][0] += 1;
			$transinfo[$row[2]][1] += $row[1];
			$transinfo[$row[2]][3] += $row[3];
		}
		$tSum = 0;
		$tItems = 0;
		$tDollars = 0;
		foreach(array_keys($transinfo) as $k){
			$transinfo[$k][2] = round($transinfo[$k][1]/$transinfo[$k][0],2);
			$transinfo[$k][4] = round($transinfo[$k][3]/$transinfo[$k][0],2);
			$tSum += $transinfo[$k][0];
			$tItems += $transinfo[$k][1];
			$tDollars += $transinfo[$k][3];
		}
		$transinfo["Totals"] = array($tSum,$tItems,round($tItems/$tSum,2),$tDollars,round($tDollars/$tSum,2));
		$report = array();
		foreach($transinfo as $title => $info){
			array_unshift($info,$title);
			$report[] = $info;
		}
		$data[] = $report;

		$ret = preg_match_all("/[0-9]+/",$FANNIE_EQUITY_DEPARTMENTS,$depts);
		if ($ret != 0){
			/* equity departments exist */
			$depts = array_pop($depts);
			$dlist = "(";
			foreach($depts as $d){
				$dates[] = $d; // add query param
				$dlist .= '?,';
			}
			$dlist = substr($dlist,0,strlen($dlist)-1).")";

			$equityQ = $dbc->prepare("SELECT d.card_no,t.dept_name, sum(total) as total 
				FROM $dlog as d
				LEFT JOIN {$FANNIE_OP_DB}.departments as t ON d.department = t.dept_no
				WHERE d.tdate BETWEEN ? AND ?
					AND d.department IN $dlist{$shrinkageUsers}
				GROUP BY d.card_no, t.dept_name ORDER BY d.card_no, t.dept_name");
			$equityR = $dbc->execute($equityQ,$dates);
			$report = array();
			while($equityW = $dbc->fetchRow($equityR)){
				$record = array($equityW['card_no'],$equityW['dept_name'],
						sprintf('%.2f',$equityW['total']));
				$report[] = $record;
			}
			$data[] = $report;
		}
		
		return $data;
	}

	function assign_headers(){
		switch($this->multi_counter){
		case 1:
			$this->report_headers[0] = 'Tenders';
			break;
		case 2:
			$this->report_headers[0] = 'Sales';
			break;
		case 3:
			$this->report_headers[0] = 'Discounts';
			break;
		case 4:
			$this->report_headers[0] = 'Tax';
			break;
		case 5:
			$this->report_headers = array('Type','Trans','Items','Avg. Items','Amount','Avg. Amount');
			//return array();
			break;
		case 6:
			$this->report_headers = array('Mem#','Equity Type', 'Amount');
			break;
		}
    }

	function calculate_footers($data){
        // 12Apr14 Moved the switch to assign_headers() for test.
        switch($this->multi_counter){
        case 5:
            return array();
            break;
        }
		$sumQty = 0.0;
		$sumSales = 0.0;
		foreach($data as $row){
			$sumQty += $row[1];
			$sumSales += $row[2];
		}
		return array(null,$sumQty,$sumSales);
	}

	function form_content(){
		$start = date('Y-m-d',strtotime('yesterday'));
		?>
            <form action="<?php echo $_SERVER['PHP_SELF'];?>" method=get>
		<table cellspacing=4 cellpadding=4>
		<tr>
		<th>Date</th>
		<td><input type=text id=date1 name=date1 onclick="showCalendarControl(this);" value="<?php echo $start; ?>" /></td>
		</tr><tr>
		<td>Excel <input type=checkbox name=excel /></td>
		<td><input type=submit name=submit value="Submit" /></td>
		</tr>
		</table>
		</form>
		<?php
	}

}

FannieDispatch::conditionalExec();
