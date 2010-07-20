<?php
/***************************************************************
* Copyright notice
*
* (c) 2009-2010 Mario Rimann (mario@screenteam.com)
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

/**
 * Class 'tx_seminars_BackEnd_CancelEventMailForm' for the 'seminars' extension.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Mario Rimann <mario@screenteam.com>
 */
class tx_seminars_BackEnd_CancelEventMailForm extends tx_seminars_BackEnd_AbstractEventMailForm  {
	/**
	 * @var string the action of this form
	 */
	protected $action = 'cancelEvent';

	/**
	 * @var the prefix for all locallang keys for prefilling the form,
	 *      must not be empty
	 */
	protected $formFieldPrefix = 'cancelMailForm_prefillField_';

	/**
	 * Returns the label for the submit button.
	 *
	 * @return string label for the submit button, will not be empty
	 */
	protected function getSubmitButtonLabel() {
		return $GLOBALS['LANG']->getLL('cancelMailForm_sendButton');
	}

	/**
	 * Gets the content of the message body for the e-mail.
	 *
	 * @return string the content for the message body, will not be empty
	 */
	protected function getMessageBodyFormContent() {
		$result = $this->localizeSalutationPlaceholder($this->formFieldPrefix);

		if (!$this->getEvent()->isEventDate()) {
			return $result;
		}

		$builder = tx_oelib_ObjectFactory::make('tx_seminars_BagBuilder_Event');
		$builder->limitToEarliestBeginDate($GLOBALS['SIM_EXEC_TIME']);
		$builder->limitToOtherDatesForTopic($this->getOldEvent());

		$otherDateBag = $builder->build();

		if (!$otherDateBag->isEmpty()) {
			$singleViewUrl = $this->getSingleViewUrl();

			if ($singleViewUrl != '') {
				$result .= LF . LF .
					$GLOBALS['LANG']->getLL('cancelMailForm_alternativeDate') .
					' <' . $singleViewUrl . '>';

			}
		}

		return $result;
	}

	/**
	 * Gets the full URL to the single view of the current event.
	 *
	 * @return string the URL to the single view of the given event, will be
	 *                empty if no single view URL could be determined
	 */
	private function getSingleViewUrl() {
		$event = $this->getEvent();

		if (!$event->hasDetailsPage()) {
			$result = $this->getUrlForPid(
				tx_oelib_ConfigurationRegistry::get('plugin.tx_seminars_pi1')
					->getAsInteger('detailPID')
			);
		} else {
			$separatePage = $event->getDetailsPage();
			if (intval($separatePage) > 0) {
				$result = $this->getUrlForPid($separatePage);
			} else {
				$result = $separatePage;
			}
		}

		if ($result == '') {
			$this->setErrorMessage(
				'messageBody',
				$GLOBALS['LANG']->getLL('eventMailForm_error_noDetailsPageFound')
			);
		}

		return $result;
	}

	/**
	 * Creates the URL to the given page ID.
	 *
	 * @param integer $pageId
	 *        the UID of the page to get the URL for, must be >= 0
	 *
	 * @return string the URL to the single view page, will be empty if 0 has
	 *                been given as page ID
	 */
	private function getUrlForPid($pageId) {
		if ($pageId == 0) {
			return '';
		}

		$rawUrl = array();
		preg_match(
			'/\.\.([^\'"]*)(\'|")/',
			t3lib_BEfunc::viewOnClick(
				$pageId, '' ,'' ,'' ,'' ,
				'&tx_seminars_pi1[showUid]=' . $this->getEvent()->getUid()
			),
			$rawUrl
		);

		return t3lib_div::locationHeaderUrl(
			preg_replace(
				array('/\[/', '/\]/'), array('%5B', '%5D'), $rawUrl[1]
			)
		);
	}

	/**
	 * Marks an event according to the status to set and commits the change to
	 * the database.
	 */
	protected function setEventStatus() {
		$this->getEvent()->setStatus(tx_seminars_Model_Event::STATUS_CANCELED);
		tx_oelib_MapperRegistry::get('tx_seminars_Mapper_Event')
			->save($this->getEvent());

		$message = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			$GLOBALS['LANG']->getLL('message_eventCanceled'),
			'',
			t3lib_FlashMessage::OK,
			TRUE
		);
		t3lib_FlashMessageQueue::addMessage($message);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/BackEnd/CancelEventMailForm.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/BackEnd/CancelEventMailForm.php']);
}
?>