<?php
/***************************************************************
* Copyright notice
*
* (c) 2008-2009 Niels Pardon (mail@niels-pardon.de)
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('seminars') . 'lib/tx_seminars_constants.php');

/**
 * Class 'tx_seminars_registrationEditorChild' for the 'seminars' extension.
 *
 * This is mere a class used for unit tests of the 'seminars' extension. Don't
 * use it for any other purpose.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
final class tx_seminars_registrationEditorChild extends tx_seminars_pi1_registrationEditor {
	/**
	 * The constructor.
	 *
	 * @param tx_seminars_pi1 the pi1 object where this registration
	 * editor will be inserted
	 */
	public function __construct(tx_seminars_pi1 $plugin) {
		$this->plugin = $plugin;
		$this->cObj = $plugin->cObj;
		$this->seminar = $plugin->getSeminar();

		$this->init($this->plugin->conf);
	}

	/**
	 * Saves the following data to the FE user session:
	 * - payment method
	 * - account number
	 * - bank code
	 * - bank name
	 * - account_owner
	 * - gender
	 * - name
	 * - address
	 * - zip
	 * - city
	 * - country
	 * - telephone
	 * - email
	 *
	 * @param array the form data (may be empty)
	 */
	public function saveDataToSession(array $parameters) {
		parent::saveDataToSession($parameters);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminarst/tests/fixtures/class.tx_seminars_registrationEditorChild.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/tests/fixtures/class.tx_seminars_registrationEditorChild.php']);
}
?>