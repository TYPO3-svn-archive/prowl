<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Frank Nägler <typo3@naegler.net>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

class tx_prowl_hooks {
	var $elementsToDelete = array();
	
	// update check
	function processDatamap_postProcessFieldArray($status, $table, $id, &$fieldArray, &$reference) {
		$this->checkForProwlMessage($status, $table, $id);
	}

	// new check
	function processDatamap_afterDatabaseOperations($status, $table, $id, &$fieldArray, &$reference) {
		$id = $reference->substNEWwithIDs[$id];
		$this->checkForProwlMessage($status, $table, $id);
	}

	// delete check
	function processCmdmap_preProcess($command, $table, $id, $value, $reference) {
		$this->elementsToDelete["{$table}_{$id}"] = t3lib_BEfunc::getRecord ($table, $id);
	}
	
	function processCmdmap_postProcess($command, $table, $id, $value, $reference) {
		$this->checkForProwlMessage($command, $table, $id);
	}
	
	// check if a prowl message should be send
	function checkForProwlMessage($status, $table, $id) {
		if ($status == 'delete') {
			$row = $this->elementsToDelete["{$table}_{$id}"];
		} else {
			$row = t3lib_BEfunc::getRecord ($table, $id);
		}
		if (isset($row['pid'])) {
			if ($this->isProwlActive($table, $status, $row['pid'])) {
				$this->sendProwlMessage($table, $status, $row);
			}
		}
	}

	// page TSConfig check
	function isProwlActive($table, $status, $pid) {
		$TSconfig = t3lib_BEfunc::getModTSconfig($pid, 'mod.prowl.tables');
		$TSconfig = $TSconfig['properties'];
		return ($TSconfig[$table.'.'][$status] == 1);
	}
	
	// send the prowl message
	function sendProwlMessage($table, $status, $row) {
		$TSconfig = t3lib_BEfunc::getModTSconfig($row['pid'], 'mod.prowl.tables');
		$TSconfig = $TSconfig['properties'];
		$message = "record ###TABLE###_###UID### with status: ###STATUS###";
		if (isset($TSconfig[$table.'.'][$status.'.']['message'])) {
			$message = $TSconfig[$table.'.'][$status.'.']['message'];
		}
		$markerArray = array();
		if (is_array($row)) {
			foreach ($row as $key => $value) {
				$key = strtoupper($key);
				$markerArray["###{$key}###"] = $value;
			}
		}
		$markerArray['###TABLE###'] = $table;
		$markerArray['###STATUS###'] = $status;
		
		foreach ($markerArray as $marker => $value) {
			$message = str_replace($marker, $value, $message);
		} 
		
		$prowl = t3lib_div::makeInstanceService('prowl_api');
		$prowl->sendProwlMessage($message);
	}
}

?>