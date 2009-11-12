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
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_tslib.'class.tslib_pibase.php');

/**
 * Plugin 'Prowl - message - box' for the 'prowl' extension.
 *
 * @author	Frank Nägler <typo3@naegler.net>
 * @package	TYPO3
 * @subpackage	tx_prowl
 */
class tx_prowl_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_prowl_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_prowl_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'prowl';	// The extension key.
	var $apiKey        = '';
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj = 1;
		$this->pi_initPIflexForm();
		$this->lConf = array();

		$piFlexForm = $this->cObj->data['pi_flexform'];
		foreach ( $piFlexForm['data'] as $sheet => $data ) {
			foreach ( $data as $lang => $value ) {
				foreach ( $value as $key => $val ) {
					$this->lConf[$key] = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
				}
			}
		}
		
		$this->extConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['prowl']);
		
		$this->apiKey = $this->getConfigurationValue('apiKey');
		
		$this->templateCode 	= $this->cObj->fileResource($this->getConfigurationValue('template'));
		
		$this->application		= $this->getConfigurationValue('application');
		$this->event			= $this->getConfigurationValue('event');
		$this->priorities		= $this->bitsToArray($this->getConfigurationValue('priorities'));
		$this->defaultPriority	= $this->getConfigurationValue('default_priority');

		if (isset($this->piVars['message'])) {
			$content = $this->sendProwlMessage();
		} else {
			$content = $this->getForm();
		}

		return $content;
	}
	
	function getConfigurationValue($key) {
		$value = $this->extConfiguration[$key];
		if (isset($this->conf[$key])) {
			$value = $this->conf[$key];
		}
		if (isset($this->lConf[$key])) {
			$value = $this->lConf[$key];
		}
		return $value;
	}
	
	function sendProwlMessage() {
		$template	= $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_SEND###');
		$lllMarker	= $this->generateLanguageMarker();
		$formMarker	= $this->generateFormMarker();
		$marker = array_merge($lllMarker, $formMarker);
		
		$prowl = t3lib_div::makeInstanceService('prowl_api');
		if ($prowl->sendProwlMessage($this->getMessage(), $this->getProwlPriority(), $this->application, $this->event, $this->apiKey, $this->providerKey)) {
			$marker['###LLL_MESSAGE_SEND###'] = $marker['###LLL_MESSAGE_SEND_SUCCESS###'];
		} else {
			$marker['###LLL_MESSAGE_SEND###'] = $marker['###LLL_MESSAGE_SEND_ERROR###'] . $prowl->getError();
		}
		
		return $this->cObj->substituteMarkerArray($template, $marker);
	}
	
	function getForm() {
		$template = $this->cObj->getSubpart($this->templateCode, '###TEMPLATE_FORM###');
		$lllMarker	= $this->generateLanguageMarker();
		$formMarker	= $this->generateFormMarker();
		$marker = array_merge($lllMarker, $formMarker);
		return $this->cObj->substituteMarkerArray($template, $marker);
	}
	
	function getMessage() {
		$message = $this->piVars['message'];
		$marker = $this->generateLanguageMarker();
		$marker['###DATE###'] = strftime($this->getConfigurationValue('dateFormat'));
		$marker['###URL###']  = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . $this->pi_getPageLink($GLOBALS['TSFE']->id);
		$marker['###MESSAGE###'] = $message;
		return $this->cObj->substituteMarkerArray($this->getConfigurationValue('messageFormat'), $marker);
	}
	
	function getProwlPriority() {
		$priorities = array(
			'1'		=> -2,
			'2'		=> -1,
			'4'		=> 0,
			'8'		=> 1,
			'16'	=> 2
		);
		$priority = $this->piVars['priority'];
		if (in_array($priority, $this->priorities)) {
			return $priorities[$priority];
		}
		return $priorities[$this->defaultPriority];
	}
	
	function bitsToArray($bits) {
		$ret = array();
		for($i=0; $i<5; $i++) {
			$ret[$i]= ($bits & pow(2,$i)) ? true : false;
		}
		return $ret;
	}

	function generateFormMarker() {
		$priorityOptions = array();
		$bit = 1;
		foreach ($this->priorities as $active) {
			if ($active) {
				$selected = ($bit == $this->defaultPriority) ? ' selected="selected"' : '';
				$priorityOptions[] = '<option value="'.$bit.'" '.$selected.'>'.$this->pi_getLL("field_priorities_{$bit}") . '</option>';
			}
			$bit = $bit*2;
		}
		$prioritySelector = '<select name="tx_prowl_pi1[priority]">';
		$prioritySelector .= implode("\n", $priorityOptions);
		$prioritySelector .= '</select>';
		
		$marker = array(
			'###FIELD_PRIORITY###'	=> $prioritySelector,
			'###FORM_ACTION###'		=> $this->pi_getPageLink($GLOBALS['TSFE']->id)
		);
		return $marker;
	}
	
	function generateLanguageMarker() {
		$this->languageMarker = array();
		if (
			(is_array($this->LOCAL_LANG[$GLOBALS['TSFE']->tmpl->setup['config.']['language']]))
			&&
			(is_array($this->LOCAL_LANG['default']))
		) {
			$markerArr = array_merge($this->LOCAL_LANG['default'],
			$this->LOCAL_LANG[$GLOBALS['TSFE']->tmpl->setup['config.']['language']]);
		} elseif (is_array($this->LOCAL_LANG['default'])) {
			$markerArr=$this->LOCAL_LANG['default'];
		} else {
			$markerArr=$this->LOCAL_LANG[$GLOBALS['TSFE']->tmpl->setup['config.']['language']];
		}
		
		while(list($k,$v)=each($markerArr)) {
			$this->languageMarker['###LLL_'.strtoupper($k).'###'] = $this->pi_getLL($k);
		}
		return $this->languageMarker;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/prowl/pi1/class.tx_prowl_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/prowl/pi1/class.tx_prowl_pi1.php']);
}

?>