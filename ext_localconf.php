<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

t3lib_extMgm::addService($_EXTKEY,  'prowl_api' /* sv type */,  'tx_prowl_api' /* sv key */,
	array(
		'title' => 'Prowl API',
		'description' => 'This service provides methods to send prowl message over the HTTP API of Prowl',

		'subtype' => '',

		'available' => TRUE,
		'priority' => 50,
		'quality' => 50,

		'os' => '',
		'exec' => '',

		'classFile' => t3lib_extMgm::extPath($_EXTKEY).'api/class.tx_prowl_api.php',
		'className' => 'tx_prowl_api',
	)
);

t3lib_extMgm::addPItoST43($_EXTKEY, 'pi1/class.tx_prowl_pi1.php', '_pi1', 'list_type', 0);

// Hooks
// update and new:
// >> processDatamapClass
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:prowl/class.tx_prowl_hooks.php:tx_prowl_hooks';

// delete
// >> processCmdmapClass
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = 'EXT:prowl/class.tx_prowl_hooks.php:tx_prowl_hooks';

?>