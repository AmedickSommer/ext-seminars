<?php
/***************************************************************
* Copyright notice
*
* (c) 2005-2007 Oliver Klee (typo3-coding@oliverklee.de)
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
 * Class 'tx_seminars_seminar' for the 'seminars' extension.
 *
 * This class represents a seminar (or similar event).
 *
 * @author	Oliver Klee <typo3-coding@oliverklee.de>
 */

require_once(t3lib_extMgm::extPath('seminars').'class.tx_seminars_objectfromdb.php');

class tx_seminars_seminar extends tx_seminars_objectfromdb {
	/** Same as class name */
	var $prefixId = 'tx_seminars_seminar';
	/**  Path to this script relative to the extension dir. */
	var $scriptRelPath = 'class.tx_seminars_seminar.php';

	/** Organizers data as an array of arrays with their UID as key. Lazily initialized. */
	var $organizersCache = array();

	/**
	 * The number of paid attendances.
	 * This variable is only available directly after updateStatistics() has been called.
	 * It will go completely away once we have a configuration about whether to count
	 * only the paid or all attendances.
	 */
	var $numberOfAttendancesPaid = 0;

	/**
	 * The related topic record as a reference to the object.
	 * This will be null if we are not a date record.
	 */
	var $topic;

	/**
	 * The constructor. Creates a seminar instance from a DB record.
	 *
	 * @param	integer		The UID of the seminar to retrieve from the DB.
	 * 						This parameter will be ignored if $dbResult is provided.
	 * @param	pointer		MySQL result pointer (of SELECT query)/DBAL object.
	 * 						If this parameter is provided, $uid will be ignored.
	 *
	 * @access	public
	 */
	function tx_seminars_seminar($seminarUid, $dbResult = null) {
		$this->init();
		$this->tableName = $this->tableSeminars;

		if (!$dbResult) {
			$dbResult = $this->retrieveRecord($seminarUid);
		}

		if ($dbResult && $GLOBALS['TYPO3_DB']->sql_num_rows($dbResult)) {
			$this->getDataFromDbResult($GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult));
		}

		// For date records: Create a reference to the topic record.
		if ($this->isEventDate()) {
			$this->topic =& $this->retrieveTopic();
			// To avoid infinite loops, null out $this->topic if it is a date record, too.
			// Date records that fail the check isTopicOkay() are used as a complete event record.
			if ($this->isTopicOkay() && $this->topic->isEventDate()) {
				$this->topic = null;
			}
		} else {
			$this->topic = null;
		}

		return;
	}

	/**
	 * Creates a hyperlink to this seminar details page. The content of the provided
	 * fieldn ame will be fetched from the event record, wrapped with link tags and
	 * returned as a link to the detailed page.
	 *
	 * If $this->conf['detailPID'] (and the corresponding flexforms value) is not set or 0,
	 * the link will point to the list view page.
	 *
	 * @param	object		a tx_seminars_templatehelper object (for a live page) which we can call pi_list_linkSingle() on (must not be null)
	 * @param	string		the name of the field to retrieve and wrap, may not be empty
	 *
	 * @return	string		HTML code for the link to the event details page
	 *
	 * @access	public
	 */
	function getLinkedFieldValue(&$plugin, $fieldName) {
		$linkedText = '';
		$detailPID = $plugin->getConfValueInteger('detailPID');

		// Certain fields can be retrieved 1:1 from the database, some need
		// to be fetched by a special getter function.
		switch ($fieldName) {
			case 'date':
				$linkedText = $this->getDate();
				break;
			default:
				$linkedText = $this->getTopicString($fieldName);
				break;
		}

		return $plugin->cObj->getTypoLink(
			$linkedText,
			($detailPID) ? $detailPID : $plugin->getConfValueInteger('listPID'),
			array('tx_seminars_pi1[showUid]' => $this->getUid())
		);
	}

	/**
	 * Gets our topic's title. For date records, this will return the
	 * corresponding topic record's title.
	 *
	 * @return	string	our topic title (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getTitle() {
		return $this->getTopicString('title');
	}

	/**
	 * Gets our direct title. Even for date records, this will return our
	 * direct title (which is visible in the back end) instead of the
	 * corresponding topic record's title.
	 *
	 * @return	string	our direct title (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getRealTitle() {
		return parent::getTitle();
	}

	/**
	 * Gets our subtitle.
	 *
	 * @return	string		our seminar subtitle (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getSubtitle() {
		return $this->getTopicString('subtitle');
	}

	/**
	 * Checks whether we have a subtitle.
	 *
	 * @return	boolean		true if we have a non-empty subtitle, false otherwise.
	 *
	 * @access	public
	 */
	function hasSubtitle() {
		return $this->hasTopicString('subtitle');
	}

	/**
	 * Gets our description, complete as RTE'ed HTML.
	 *
	 * @param	object		the live pibase object
	 *
	 * @return	string		our seminar description (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getDescription(&$plugin) {
		return $plugin->pi_RTEcssText($this->getTopicString('description'));
	}

	/**
	 * Checks whether we have a description.
	 *
	 * @return	boolean		true if we have a non-empty description, false otherwise.
	 *
	 * @access	public
	 */
	function hasDescription() {
		return $this->hasTopicString('description');
	}

	/**
	 * Checks whether this event has additional informations for times and places set.
	 *
	 * @return	boolean		true if the field "times_places" is not empty
	 *
	 * @access	public
	 */
	function hasAdditionalTimesAndPlaces() {
		return $this->hasRecordPropertyString('additional_times_places');
	}

	/**
	 * Returns the content of the field "times_places" for this event.
	 * The line breaks of this non-RTE field are replaced with "<br />" for the
	 * HTML output.
	 *
	 * @return	string		the field content
	 *
	 * @access	public
	 */
	function getAdditionalTimesAndPlaces() {
		$additionalTimesAndPlaces = htmlspecialchars($this->getRecordPropertyString('additional_times_places'));
		$result = str_replace(chr(13).chr(10), '<br />', $additionalTimesAndPlaces);

		return $result;
	}

	/**
	 * Gets the additional information, complete as RTE'ed HTML.
	 *
	 * @param	object		the live pibase object
	 *
	 * @return	string		HTML code of the additional information (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getAdditionalInformation(&$plugin) {
		return $plugin->pi_RTEcssText($this->getTopicString('additional_information'));
	}

	/**
	 * Checks whether we have additional information for this event.
	 *
	 * @return	boolean		true if we have additional information (field not empty), false otherwise.
	 *
	 * @access	public
	 */
	function hasAdditionalInformation() {
		return $this->hasTopicString('additional_information');
	}

	/**
	 * Gets the unique seminar title, consisting of the seminar title and the date
	 * (comma-separated).
	 *
	 * If the seminar has no date, just the title is returned.
	 *
	 * @param	string		the character or HTML entity used to separate start date and end date
	 *
	 * @return	string		the unique seminar title (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getTitleAndDate($dash = '&#8211;') {
		$date = $this->hasDate() ? ', '.$this->getDate($dash) : '';

		return $this->getTitle().$date;
	}

	/**
	 * Gets the accreditation number (which actually is a string, not an integer).
	 *
	 * @return	string		the accreditation number (may be empty)
	 *
	 * @access	public
	 */
	function getAccreditationNumber() {
		return $this->getRecordPropertyString('accreditation_number');
	}

	/**
	 * Checks whether we have an accreditation number set.
	 *
	 * @return	boolean		true if we have a non-empty accreditation number, false otherwise.
	 *
	 * @access	public
	 */
	function hasAccreditationNumber() {
		return $this->hasRecordPropertyString('accreditation_number');
	}

	/**
	 * Gets the number of credit points for this seminar
	 * (or an empty string if it is not set yet).
	 *
	 * @return	string		the number of credit points (or a an empty string if it is 0)
	 *
	 * @access	public
	 */
	function getCreditPoints() {
		return $this->hasCreditPoints() ? $this->getTopicInteger('credit_points') : '';
	}

	/**
	 * Checks whether this seminar has a non-zero number of credit points assigned.
	 *
	 * @return	boolean		true if the seminar has credit points assigned, false otherwise.
	 *
	 * @access	public
	 */
	function hasCreditPoints() {
		return $this->hasTopicInteger('credit_points');
	}

 	/**
	 * Creates part of a WHERE clause to select events that start later the same
	 * day the current event ends or the day after.
	 * The return value of this function always starts with " AND" (except for
	 * when this event has no end date).
	 *
	 * @return	string		part of a WHERE clause that can be appended to the current WHERE clause (or an empty string if this event has no end date)
	 *
	 * @access	public
	 */
	function getAdditionalQueryForNextDay() {
		$result = '';

		if ($this->hasEndDate()) {
			// 86400 seconds are one day.
			$oneDay = 86400;
			$endDate = $this->getRecordPropertyInteger('end_date');
			$midnightBeforeEndDate = $endDate - ($endDate % $oneDay);
			$secondMidnightAfterEndDate = $midnightBeforeEndDate + 2 * $oneDay;

			$result = ' AND begin_date>='.$endDate.
				' AND begin_date<'.$secondMidnightAfterEndDate;
		}

		return $result;
	}

 	/**
	 * Creates part of a WHERE clause to select other dates for the current
	 * topic. The return value of this function always starts with " AND". When
	 * it is used, the DB query will select records of the same topic that
	 * are not identical (ie. not with the same UID) with the current event.
	 *
	 * @return	string		part of a WHERE clause that can be appended to the current WHERE clause
	 *
	 * @access	public
	 */
	function getAdditionalQueryForOtherDates() {
		$result = ' AND (';

		if ($this->getRecordPropertyInteger('object_type') == $this->recordTypeDate) {
			$result .= '(topic='.$this->getRecordPropertyInteger('topic').' AND '
					.'uid!='.$this->getUid().')'
				.' OR '
					.'(uid='.$this->getRecordPropertyInteger('topic')
					.' AND object_type='.$this->recordTypeComplete.')';
		} else {
			$result .= 'topic='.$this->getUid()
				.' AND object_type!='.$this->recordTypeComplete;
		}

		$result .= ')';

		return $result;
	}

	/**
	 * Gets the seminar date.
	 * Returns a localized string "will be announced" if the seminar has no date set.
	 *
	 * Returns just one day if the seminar takes place on only one day.
	 * Returns a date range if the seminar takes several days.
	 *
	 * @param	string		the character or HTML entity used to separate start date and end date
	 *
	 * @return	string		the seminar date
	 *
	 * @access	public
	 */
	function getDate($dash = '&#8211;') {
		if (!$this->hasDate()) {
			$result = $this->pi_getLL('message_willBeAnnounced');
		} else {
			$beginDate = $this->getRecordPropertyInteger('begin_date');
			$endDate = $this->getRecordPropertyInteger('end_date');

			$beginDateDay = strftime($this->getConfValueString('dateFormatYMD'), $beginDate);
			$endDateDay = strftime($this->getConfValueString('dateFormatYMD'), $endDate);

			// Does the workshop span only one day (or is open-ended)?
			if (($beginDateDay == $endDateDay) || !$this->hasEndDate()) {
				$result = $beginDateDay;
			} else {
				if (!$this->getConfValueBoolean('abbreviateDateRanges')) {
					$result = $beginDateDay;
				} else {
					// Are the years different? Then include the complete begin date.
					if (strftime($this->getConfValueString('dateFormatY'), $beginDate) !== strftime($this->getConfValueString('dateFormatY'), $endDate)) {
						$result = $beginDateDay;
					} else {
						// Are the months different? Then include day and month.
						if (strftime($this->getConfValueString('dateFormatM'), $beginDate) !== strftime($this->getConfValueString('dateFormatM'), $endDate)) {
							$result = strftime($this->getConfValueString('dateFormatMD'), $beginDate);
						} else {
							$result = strftime($this->getConfValueString('dateFormatD'), $beginDate);
						}
					}
				}
				$result .= $dash.$endDateDay;
			}
		}

		return $result;
	}

	/**
	 * Checks whether the seminar has a (begin) date set.
	 * If the seminar has an end date but no begin date,
	 * this function still will return false.
	 *
	 * @return	boolean		true if we have a begin date, false otherwise.
	 *
	 * @access	public
	 */
	function hasDate() {
		return $this->hasRecordPropertyInteger('begin_date');
	}

	/**
	 * Checks whether the seminar has an end date set
	 *
	 * @return	boolean		true if we have an end date, false otherwise.
	 *
	 * @access	public
	 */
	function hasEndDate() {
		return $this->hasRecordPropertyInteger('end_date');
	}

	/**
	 * Gets the seminar time.
	 * Returns a localized string "will be announced" if the seminar has no time set
	 * (i.e. both begin time and end time are 00:00).
	 * Returns only the begin time if begin time and end time are the same.
	 *
	 * @param	string		the character or HTML entity used to separate begin time and end time
	 *
	 * @return	string		the seminar time
	 *
	 * @access	public
	 */
	function getTime($dash = '&#8211;') {
		if (!$this->hasTime()) {
			$result = $this->pi_getLL('message_willBeAnnounced');
		} else {
			$beginDate = $this->getRecordPropertyInteger('begin_date');
			$endDate = $this->getRecordPropertyInteger('end_date');

			$beginTime = strftime($this->getConfValueString('timeFormat'), $beginDate);
			$endTime = strftime($this->getConfValueString('timeFormat'), $endDate);

			$result = $beginTime;

			// Only display the end time if the event has an end date/time set
			// and the end time is not the same as the begin time.
			if ($this->hasEndTime() && ($beginTime !== $endTime)) {
				$result .= $dash.$endTime;
			}
		}

		return $result;
	}

	/**
	 * Checks whether the seminar has a time set (begin time != 00:00)
	 * If the event has no date/time set, the result will be false.
	 *
	 * @return	boolean		true if we have a begin time, false otherwise
	 *
	 * @access	public
	 */
	function hasTime() {
		$beginTime = strftime('%H:%M', $this->getRecordPropertyInteger('begin_date'));

		return ($this->hasDate() && ($beginTime !== '00:00'));
	}

	/**
	 * Checks whether the event has an end time set (end time != 00:00)
	 * If the event has no end date/time set, the result will be false.
	 *
	 * @return	boolean		true if we have an end time, false otherwise
	 *
	 * @access	public
	 */
	function hasEndTime() {
		$endTime = strftime('%H:%M', $this->getRecordPropertyInteger('end_date'));

		return ($this->hasEndDate() && ($endTime !== '00:00'));
	}

	/**
	 * Gets our place (or places), complete as RTE'ed HTML with address and links.
	 * Returns a localized string "will be announced" if the seminar has no places set.
	 *
	 * @param	object		the live pibase object
	 *
	 * @return	string		our places description (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getPlaceWithDetails(&$plugin) {
		$result = '';

		if ($this->hasPlace()) {
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'title, address, homepage, directions',
				$this->tableSites.', '.$this->tableSitesMM,
				'uid_local='.$this->getUid().' AND uid=uid_foreign'
					.t3lib_pageSelect::enableFields($this->tableSites),
				'',
				'',
				''
			);

			if ($dbResult) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult)) {
					$name = $row['title'];
					if (!empty($row['homepage'])) {
						$name = $plugin->cObj->getTypoLink($name, $row['homepage']);
					}
					$plugin->setMarkerContent('place_item_title', $name);

					$description = '';
					if (!empty($row['address'])) {
						// replace all occurrences of chr(13) (new line) with a comma
						$description .= str_replace(chr(13), ',', $row['address']);
					}
					if (!empty($row['directions'])) {
						$description .= $plugin->pi_RTEcssText($row['directions']);
					}
					$plugin->setMarkerContent('place_item_description', $description);

					$result .= $plugin->substituteMarkerArrayCached('PLACE_LIST_ITEM');
				}
			}
		} else {
			$result = $this->pi_getLL('message_willBeAnnounced');
		}

		return $result;
	}

	/**
	 * Gets our place (or places) as a plain test list (just the place names).
	 * Returns a localized string "will be announced" if the seminar has no places set.
	 *
	 * @return	string		our places list (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getPlaceShort() {
		$result = '';

		if ($this->hasPlace()) {
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'title',
				$this->tableSites.', '.$this->tableSitesMM,
				'uid_local='.$this->getUid().' AND uid=uid_foreign'
					.t3lib_pageSelect::enableFields($this->tableSites),
				'',
				'',
				''
			);

			if ($dbResult) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult)) {
					if (!empty($result)) {
						$result .= ', ';
					}

					$result .= $row['title'];
				}
			}
		} else {
			$result = $this->pi_getLL('message_willBeAnnounced');
		}

		return $result;
	}

	/**
	 * Checks whether we have a place (or places) set.
	 *
	 * @return	boolean		true if we have a non-empty places list, false otherwise.
	 *
	 * @access	public
	 */
	function hasPlace() {
		return $this->hasRecordPropertyInteger('place');
	}

	/**
	 * Gets the seminar room (not the site).
	 *
	 * @return	string		the seminar room (may be empty)
	 *
	 * @access	public
	 */
	function getRoom() {
		return $this->getRecordPropertyString('room');
	}

	/**
	 * Checks whether we have a room set.
	 *
	 * @return	boolean		true if we have a non-empty room, false otherwise.
	 *
	 * @access	public
	 */
	function hasRoom() {
		return $this->hasRecordPropertyString('room');
	}

	/**
	 * Gets our speaker (or speakers), complete as RTE'ed HTML with details and links.
	 * Returns an empty paragraph if this seminar doesn't have any speakers.
	 *
	 * @param	object		the live pibase object
	 *
	 * @return	string		our speakers (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getSpeakersWithDescription(&$plugin) {
		$result = '';

		if ($this->hasSpeakers()) {
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'title, organization, homepage, description',
				$this->tableSpeakers.', '.$this->tableSpeakersMM,
				'uid_local='.$this->getUid().' AND uid=uid_foreign'
					.t3lib_pageSelect::enableFields($this->tableSpeakers),
				'',
				'',
				''
			);

			if ($dbResult) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult)) {
					$name = $row['title'];
					if (!empty($row['organization'])) {
						$name .= ', '.$row['organization'];
					}
					if (!empty($row['homepage'])) {
						$name = $plugin->cObj->getTypoLink($name, $row['homepage']);
					}
					$plugin->setMarkerContent('speaker_item_title', $name);

					if (!empty($row['description'])) {
						$description = $plugin->pi_RTEcssText($row['description']);
					}
					$plugin->setMarkerContent('speaker_item_description', $description);

					$result .= $plugin->substituteMarkerArrayCached('SPEAKER_LIST_ITEM');
				}
			}
		}

		return $result;
	}

	/**
	 * Gets our speaker (or speakers) as a plain text list (just their names).
	 * Returns an empty string if this seminar doesn't have any speakers.
	 *
	 * @return	string		our speakers list (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getSpeakersShort() {
		$result = '';

		if ($this->hasSpeakers()) {
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'title',
				$this->tableSpeakers.', '.$this->tableSpeakersMM,
				'uid_local='.$this->getUid().' AND uid=uid_foreign'
					.t3lib_pageSelect::enableFields($this->tableSpeakers),
				'',
				'',
				''
			);

			if ($dbResult) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult)) {
					if (!empty($result)) {
						$result .= ', ';
					}

					$result .= $row['title'];
				}
			}
		}

		return $result;
	}

	/**
	 * Checks whether we have any speakers set, but does not check the validity of that entry.
	 *
	 * @return	boolean		true if we have any speakers asssigned to this seminar, false otherwise.
	 *
	 * @access	public
	 */
	function hasSpeakers() {
		return $this->hasRecordPropertyInteger('speakers');
	}

	/**
	 * Gets our regular price as a string containing amount and currency.
	 *
	 * @param	string		the character or HTML entity used to separate price and currency
	 *
	 * @return	string		the regular seminar price
	 *
	 * @access	public
	 */
	function getPriceRegular($space = '&nbsp;') {
		$value = $this->getTopicDecimal('price_regular');
		$currency = $this->getConfValueString('currency');
		return $this->formatPrice($value).$space.$currency;
	}

	/**
	 * Returns the price, formatted as configured in TS.
	 * The price must be supplied as integer or floating point value.
	 *
	 * @param	string		the price
	 *
	 * @return	string		the price, formatted as in configured in TS
	 */
	function formatPrice($value) {
		return number_format($value,
			$this->getConfValueInteger('decimalDigits'),
			$this->getConfValueString('decimalSplitChar'),
			$this->getConfValueString('thousandsSplitChar'));
	}

	/**
	 * Returns the current regular price for this event.
	 * If there is a valid early bird offer, this price will be returned, otherwise the default price.
	 *
	 * @param	string		the character or HTML entity used to separate price and currency
	 *
	 * @return	string		the price and the currency
	 *
	 * @access	protected
	 */
	function getCurrentPriceRegular($space = '&nbsp;') {
		return ($this->earlyBirdApplies()) ? $this->getEarlyBirdPriceRegular($space) : $this->getPriceRegular($space);
	}

	/**
	 * Returns the current price for this event.
	 * If there is a valid early bird offer, this price will be returned, the default special price otherwise.
	 *
	 * @param	string		the character or HTML entity used to separate price and currency
	 *
	 * @return	string		the price and the currency
	 *
	 * @access	protected
	 */
	function getCurrentPriceSpecial($space = '&nbsp;') {
		return ($this->earlyBirdApplies()) ? $this->getEarlyBirdPriceSpecial($space) : $this->getPriceSpecial($space);
	}

	/**
	 * Gets our regular price during the early bird phase as a string containing
	 * amount and currency.
	 *
	 * @param	string		the character or HTML entity used to separate price and currency
	 *
	 * @return	string		the regular early bird event price
	 *
	 * @access	protected
	 */
	function getEarlyBirdPriceRegular($space = '&nbsp;') {
		$value = $this->getTopicDecimal('price_regular_early');
		$currency = $this->getConfValueString('currency');
		return $this->hasEarlyBirdPriceRegular() ?
			$this->formatPrice($value).$space.$currency : '';
	}

	/**
	 * Gets our special price during the early bird phase as a string containing
	 * amount and currency.
	 *
	 * @param	string		the character or HTML entity used to separate price and currency
	 *
	 * @return	string		the regular early bird event price
	 *
	 * @access	protected
	 */
	function getEarlyBirdPriceSpecial($space = '&nbsp;') {
		$value = $this->getTopicDecimal('price_special_early');
		$currency = $this->getConfValueString('currency');
		return $this->hasEarlyBirdPriceSpecial() ?
			$this->formatPrice($value).$space.$currency : '';
	}

	/**
	 * Checks whether this seminar has a non-zero regular price set.
	 *
	 * @return	boolean		true if the seminar has a non-zero regular price, false if it is free.
	 *
	 * @access	public
	 */
	function hasPriceRegular() {
		return $this->hasTopicDecimal('price_regular');
	}

	/**
	 * Checks whether this seminar has a non-zero regular early bird price set.
	 *
	 * @return	boolean		true if the seminar has a non-zero regular early bird price, false otherwise
	 *
	 * @access	protected
	 */
	function hasEarlyBirdPriceRegular() {
		return $this->hasTopicDecimal('price_regular_early');
	}

	/**
	 * Checks whether this seminar has a non-zero special early bird price set.
	 *
	 * @return	boolean		true if the seminar has a non-zero special early bird price, false otherwise
	 *
	 * @access	protected
	 */
	function hasEarlyBirdPriceSpecial() {
		return $this->hasTopicDecimal('price_special_early');
	}

	/**
	 * Checks whether this event has a deadline for the early bird prices set.
	 *
	 * @return	boolean		true if the event has an early bird deadline set, false if not
	 *
	 * @access	protected
	 */
	function hasEarlyBirdDeadline() {
		return $this->hasRecordPropertyInteger('deadline_early_bird');
	}

	/**
	 * Returns whether an early bird price applies.
	 *
	 * @return	boolean		true if this event has an early bird dealine set and this deadline is not over yet
	 *
	 * @access	protected
	 */
	function earlyBirdApplies() {
		return ($this->hasEarlyBirdPrice() && !$this->isEarlyBirdDeadlineOver());
	}

	/**
	 * Checks whether this event is sold with early bird prices.
	 *
	 * This will return true if the event has a deadline and a price defined
	 * for early-bird registrations. If the special price (e.g. for students)
	 * is not used, then the student's early bird price is not checked.
	 *
	 * Attention: Both prices (standard and special) need to have an early bird
	 * version for this function to return true (if there is a regular special price).
	 *
	 * @return	boolean		true if an early bird deadline and early bird prices are set
	 *
	 * @access	protected
	 */
	function hasEarlyBirdPrice() {
		// whether the event has regular prices set (a normal one and an early bird)
		$priceRegularIsOk = $this->hasPriceRegular() && $this->hasEarlyBirdPriceRegular();

		// whether no special price is set, or both special prices
		// (normal and early bird) are set
		$priceSpecialIsOk = !$this->hasPriceSpecial() ||
							($this->hasPriceSpecial() && $this->hasEarlyBirdPriceSpecial());

		return ($this->hasEarlyBirdDeadline() && $priceRegularIsOk && $priceSpecialIsOk);
	}

	/**
	 * Gets our special price as a string containing amount and currency.
	 * Returns an empty string if there is no special price set.
	 *
	 * @param	string		the character or HTML entity used to separate price and currency
	 *
	 * @return	string		the special event price
	 *
	 * @access	public
	 */
	function getPriceSpecial($space = '&nbsp;') {
		$value = $this->getTopicDecimal('price_special');
		$currency = $this->getConfValueString('currency');
		return $this->hasPriceSpecial() ?
			$this->formatPrice($value).$space.$currency : '';
	}

	/**
	 * Checks whether this seminar has a non-zero special price set.
	 *
	 * @return	boolean		true if the seminar has a non-zero special price, false if it is free.
	 *
	 * @access	public
	 */
	function hasPriceSpecial() {
		return $this->hasTopicDecimal('price_special');
	}

	/**
	 * Gets our allowed payment methods, complete as RTE'ed HTML LI list (with enclosing UL),
	 * but without the detailed description.
	 * Returns an empty paragraph if this seminar doesn't have any payment methods.
	 *
	 * @param	object		the live pibase object
	 *
	 * @return	string		our payment methods as HTML (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getPaymentMethods(&$plugin) {
		$result = '';

		$paymentMethodsUids = explode(',', $this->getTopicString('payment_methods'));
		foreach ($paymentMethodsUids as $currentPaymentMethod) {
			$dbResultPaymentMethod = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'title',
				$this->tablePaymentMethods,
				'uid='.intval($currentPaymentMethod)
					.t3lib_pageSelect::enableFields($this->tablePaymentMethods),
				'',
				'',
				''
			);

			// we expect just one result
			if ($dbResultPaymentMethod && $GLOBALS['TYPO3_DB']->sql_num_rows ($dbResultPaymentMethod)) {
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResultPaymentMethod);
				$result .= '  <li>'.$row['title'].'</li>'.chr(10);
			}
		}

		$result = '<ul>'.chr(10).$result.'</ul>'.chr(10);

		return $plugin->pi_RTEcssText($result);
	}

	/**
	 * Gets our allowed payment methods, just as plain text,
	 * including the detailed description.
	 * Returns an empty string if this seminar doesn't have any payment methods.
	 *
	 * @return	string		our payment methods as plain text (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getPaymentMethodsPlain() {
		$result = '';

		$paymentMethodsUids = explode(',', $this->getTopicString('payment_methods'));

		foreach ($paymentMethodsUids as $currentPaymentMethod) {
			$result .= $this->getSinglePaymentMethodPlain($currentPaymentMethod);
		}

		return $result;
	}

	/**
	 * Get a single payment method, just as plain text, including the detailed
	 * description.
	 * Returns an empty string if the corresponding payment method could not
	 * be retrieved.
	 *
	 * @param	integer		the UID of a single payment method, must not be zero
	 *
	 * @return	string		the selected payment method as plain text (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getSinglePaymentMethodPlain($paymentMethodUid) {
		$result = '';

		$dbResultPaymentMethod = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'title, description',
			$this->tablePaymentMethods,
			'uid='.$paymentMethodUid
				.t3lib_pageSelect::enableFields($this->tablePaymentMethods),
			'',
			'',
			''
		);

		// we expect just one result
		if ($dbResultPaymentMethod && $GLOBALS['TYPO3_DB']->sql_num_rows ($dbResultPaymentMethod)) {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResultPaymentMethod);
			$result = $row['title'].': ';
			$result .= $row['description'].chr(10).chr(10);
		}

		return $result;
	}

	/**
	 * Get a single payment method, just as plain text, without the detailed
	 * description.
	 * Returns an empty string if the corresponding payment method could not
	 * be retrieved.
	 *
	 * @param	integer		the UID of a single payment method, must not be zero
	 *
	 * @return	string		the selected payment method as plain text (or '' if there is an error)
	 *
	 * @access	public
	 */
	function getSinglePaymentMethodShort($paymentMethodUid) {
		$result = '';

		$dbResultPaymentMethod = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'title',
			$this->tablePaymentMethods,
			'uid='.$paymentMethodUid
				.t3lib_pageSelect::enableFields($this->tablePaymentMethods),
			'',
			'',
			''
		);

		// we expect just one result
		if ($dbResultPaymentMethod && $GLOBALS['TYPO3_DB']->sql_num_rows ($dbResultPaymentMethod)) {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResultPaymentMethod);
			$result = $row['title'];
		}

		return $result;
	}

	/**
	 * Gets the UIDs of our allowed payment methods as a comma-separated list,
	 * Returns an empty string if this seminar doesn't have any payment methods.
	 *
	 * @return	string		our payment methods as plain text (or '' if there are no payment methods set)
	 *
	 * @access	public
	 */
	function getPaymentMethodsUids() {
		return $this->getTopicString('payment_methods');
	}

	/**
	 * Checks whether this seminar has any paxment methods set.
	 *
	 * @return	boolean		true if the seminar has any payment methods, false if it is free.
	 *
	 * @access	public
	 */
	function hasPaymentMethods() {
		return $this->hasTopicString('payment_methods');
	}

 	/**
	 * Returns the type of the record. This is one out of the following values:
	 * 0 = single event (and default value of older records)
	 * 1 = multiple event topic record
	 * 2 = multiple event date record
	 *
	 * @return	integer		the record type
	 *
	 * @access	public
	 */
	function getRecordType() {
		return $this->getRecordPropertyInteger('object_type');
	}

	/**
	 * Checks whether this seminar has an event type set.
	 *
	 * @return	boolean		true if the seminar has an event type set, false if not
	 *
	 * @access	public
	 */
	function hasEventType() {
		return $this->hasTopicInteger('event_type');
	}

	/**
	 * Returns the event type as a string (e.g. "Workshop" or "Lecture").
	 * If the seminar has a event type selected, that one is returned. Otherwise
	 * the global event type from the TS setup is returned.
	 *
	 * @return	string		the type of this event
	 *
	 * @access	public
	 */
	function getEventType() {
		$result = '';

		// Check whether this event has an event type set.
		if ($this->hasEventType()) {
			$eventTypeUid = $this->getTopicInteger('event_type');

			// Get the title of this event type.
			$dbResultEventType = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'title',
				$this->tableEventTypes,
				'uid='.$eventTypeUid
					.t3lib_pageSelect::enableFields($this->tableEventTypes),
				'',
				'',
				'1'
			);
			if ($dbResultEventType && $GLOBALS['TYPO3_DB']->sql_num_rows($dbResultEventType)) {
				$eventTypeRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResultEventType);
				$result = $eventTypeRow['title'];
			}
		}

		// Check whether an event type could be set, otherwise use the default name from TS setup.
		if (empty($result)) {
			$result = $this->getConfValueString('eventType');
		}

		return $result;
	}

	/**
	 * Gets the minimum number of attendances required for this event
	 * (ie. how many registrations are needed so this event can take place).
	 *
	 * @return	integer		the minimum number of attendances
	 *
	 * @access	public
	 */
	function getAttendancesMin() {
		return $this->getRecordPropertyInteger('attendees_min');
	}

	/**
	 * Gets the maximum number of attendances for this event
	 * (the total number of seats for this event).
	 *
	 * @return	integer		the maximum number of attendances
	 *
	 * @access	public
	 */
	function getAttendancesMax(){
		return $this->getRecordPropertyInteger('attendees_max');
	}

	/**
	 * Gets the number of attendances for this seminar
	 * (currently the paid attendances as well as the unpaid ones)
	 *
	 * @return	integer		the number of attendances
	 *
	 * @access	public
	 */
	function getAttendances() {
		return $this->getRecordPropertyInteger('attendees');
	}

	/**
	 * Gets the number of paid attendances for this seminar.
	 * This function may only be called after updateStatistics() has been called.
	 *
	 * @return	integer		the number of paid attendances
	 *
	 * @access	public
	 */
	function getAttendancesPaid() {
		return $this->numberOfAttendancesPaid;
	}

	/**
	 * Gets the number of attendances that are not paid yet
	 *
	 * @return	integer		the number of attendances that are not paid yet
	 *
	 * @access	public
	 */
	function getAttendancesNotPaid() {
		return ($this->getAttendances() - $this->getAttendancesPaid());
	}

	/**
	 * Gets the number of vacancies for this seminar.
	 *
	 * @return	integer		the number of vacancies (will be 0 if the seminar is overbooked)
	 *
	 * @access	public
	 */
	function getVacancies() {
		return max(0, $this->getRecordPropertyInteger('attendees_max') - $this->getAttendances());
	}

	/**
	 * Gets the number of vacancies for this seminar. If there are at least as
	 * many vacancies as configured as "showVacanciesThreshold", a localized
	 * string "enough" is returned instead.
	 *
	 * If this seminar does not require a registration or if it is canceled,
	 * an empty string is returned.
	 *
	 * @return	string		string showing the number of vacancies (may be empty)
	 *
	 * @access	public
	 */
	function getVacanciesString() {
		$result = '';

		if ($this->needsRegistration() && !$this->isCanceled()) {
			$result =
				($this->getVacancies() >= $this->getConfValueInteger('showVacanciesThreshold')) ?
					$this->pi_getLL('message_enough') :
					$this->getVacancies();
		}

		return $result;
	}

	/**
	 * Checks whether this seminar still has vacancies (is not full yet).
	 *
	 * @return	boolean		true if the seminar has vacancies, false if it is full.
	 *
	 * @access	public
	 */
	function hasVacancies() {
		return !($this->isFull());
	}

	/**
	 * Checks whether this seminar already is full .
	 *
	 * @return	boolean		true if the seminar is full, false if it still has vacancies.
	 *
	 * @access	public
	 */
	function isFull() {
		return $this->getRecordPropertyBoolean('is_full');
	}

	/**
	 * Checks whether this seminar has enough attendances to take place.
	 *
	 * @return	boolean		true if the seminar has enough attendances, false otherwise.
	 *
	 * @access	public
	 */
	function hasEnoughAttendances() {
		return $this->getRecordPropertyBoolean('enough_attendees');
	}

	/**
	 * Returns the latest date/time to register for a seminar.
	 * This is either the registration deadline (if set) or the begin date of an event.
	 *
	 * @return	integer		the latest possible moment to register for a seminar
	 *
	 * @access	public
	 */
	function getLatestPossibleRegistrationTime() {
		return (($this->hasRegistrationDeadline()) ?
			$this->getRecordPropertyInteger('deadline_registration') :
			$this->getRecordPropertyInteger('begin_date')
		);
	}

	/**
	 * Returns the latest date/time to register with early bird rebate for an event.
	 * The latest time to register with early bird rebate is exactly at the early bird deadline.
	 *
	 * @return	integer		the latest possible moment to register with early bird rebate for an event
	 *
	 * @access	protected
	 */
	function getLatestPossibleEarlyBirdRegistrationTime() {
		return $this->getRecordPropertyInteger('deadline_early_bird');
	}

	/**
	 * Returns the seminar registration deadline
	 * The returned string is formatted using the format configured in dateFormatYMD and timeFormat
	 *
	 * @return	string		the date + time of the deadline
	 *
	 * @access	public
	 */
	function getRegistrationDeadline() {
		$result = strftime($this->getConfValueString('dateFormatYMD'), $this->getRecordPropertyInteger('deadline_registration'));
		if ($this->getConfValueBoolean('showTimeOfRegistrationDeadline')) {
			$result .= strftime(' '.$this->getConfValueString('timeFormat'), $this->getRecordPropertyInteger('deadline_registration'));
		}
		return $result;
	}

	/**
	 * Checks whether this seminar has a deadline for registration set.
	 *
	 * @return	boolean		true if the seminar has a datetime set.
	 *
	 * @access	public
	 */
	function hasRegistrationDeadline() {
		return $this->hasRecordPropertyInteger('deadline_registration');
	}

	/**
	 * Returns the early bird deadline.
	 * The returned string is formatted using the format configured in dateFormatYMD
	 * and timeFormat.
	 *
	 * The TS parameter 'showTimeOfEarlyBirdDeadline' controls if the time should also
	 * be returned in addition to the date.
	 *
	 * @return	string		the date and time of the early bird deadline
	 *
	 * @access	protected
	 */
	function getEarlyBirdDeadline() {
		$result = strftime($this->getConfValueString('dateFormatYMD'), $this->getRecordPropertyInteger('deadline_early_bird'));
		if ($this->getConfValueBoolean('showTimeOfEarlyBirdDeadline')) {
			$result .= strftime(' '.$this->getConfValueString('timeFormat'), $this->getRecordPropertyInteger('deadline_early_bird'));
		}
		return $result;
	}

	/**
	 * Checks whether this seminar has a minimum of attendees set.
	 *
	 * @return	boolean		true if the seminar has a minimum of attendees set.
	 *
	 * @access	public
	 */
	function hasMinimumAttendees() {
		return $this->hasRecordPropertyInteger('attendees_min');
	}

	/**
	 * Returns the minimum amount of attendees required for this event to be held.
	 *
	 * @return	integer		the minimum amount of attendees
	 *
	 * @access	public
	 */
	function getMinimumAttendees() {
		return $this->getRecordPropertyInteger('attendees_min');
	}

	/**
	 * Gets our organizers (as HTML code with hyperlinks to their homepage, if they have any).
	 *
	 * @param	object		a tx_seminars_templatehelper object (for a live page, must not be null)
	 *
	 * @return	string		the hyperlinked names and descriptions of our organizers
	 *
	 * @access	public
	 */
	function getOrganizers(&$plugin) {
		$result = '';

		if ($this->hasOrganizers()) {
			$organizerUids = explode(',', $this->getRecordPropertyString('organizers'));
			foreach ($organizerUids as $currentOrganizerUid) {
				$currentOrganizerData =& $this->retrieveOrganizer($currentOrganizerUid);

				if ($currentOrganizerData) {
					if (!empty($result)) {
						$result .= ', ';
					}
					$result .= $plugin->cObj->getTypoLink($currentOrganizerData['title'], $currentOrganizerData['homepage']);
				}
			}
		}

		return $result;
	}

	/**
	 * Gets our organizers' names and e-mail addresses in the format
	 * '"John Doe" <john.doe@example.com>'.
	 *
	 * The name is not encoded yet.
	 *
	 * @return	array		the organizers' names and e-mail addresses
	 *
	 * @access	public
	 */
	function getOrganizersNameAndEmail() {
		$result = array();

		if ($this->hasOrganizers()) {
			$organizerUids = explode(',', $this->getRecordPropertyString('organizers'));
			foreach ($organizerUids as $currentOrganizerUid) {
				$currentOrganizerData =& $this->retrieveOrganizer($currentOrganizerUid);

				if ($currentOrganizerData) {
					$result[] = '"'.$currentOrganizerData['title'].'" <'.$currentOrganizerData['email'].'>';
				}
			}
		}

		return $result;
	}

	/**
	 * Gets our organizers' e-mail addresses in the format
	 * "john.doe@example.com".
	 *
	 * @return	array		the organizers' e-mail addresses
	 *
	 * @access	public
	 */
	function getOrganizersEmail() {
		$result = array();

		if ($this->hasOrganizers()) {
			$organizerUids = explode(',', $this->getRecordPropertyString('organizers'));
			foreach ($organizerUids as $currentOrganizerUid) {
				$currentOrganizerData =& $this->retrieveOrganizer($currentOrganizerUid);

				if ($currentOrganizerData) {
					$result[] = $currentOrganizerData['email'];
				}
			}
		}

		return $result;
	}

	/**
	 * Gets our organizers' e-mail footers.
	 *
	 * @return	array		the organizers' e-mail footers.
	 *
	 * @access	public
	 */
	function getOrganizersFooter() {
		$result = array();

		if ($this->hasOrganizers()) {
			$organizerUids = explode(',', $this->getRecordPropertyString('organizers'));
			foreach ($organizerUids as $currentOrganizerUid) {
				$currentOrganizerData =& $this->retrieveOrganizer($currentOrganizerUid);

				if ($currentOrganizerData) {
					$result[] = $currentOrganizerData['email_footer'];
				}
			}
		}

		return $result;
	}

	/**
	 * Retrieves an organizer from the DB and caches it in this->organizersCache.
	 * If that organizer already is in the cache, it is taken from there instead.
	 *
	 * In case of error, $this->organizersCache will stay untouched.
	 *
	 * @param	integer		UID of the organizer to retrieve
	 *
	 * @return	array		a reference to the organizer data (will be null if an error has occured)
	 *
	 * @access	private
	 */
	 function &retrieveOrganizer($organizerUid) {
	 	$result = false;

	 	if (isset($this->organizersCache[$organizerUid])) {
	 		$result = $this->organizersCache[$organizerUid];
	 	} else {
		 	$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				$this->tableOrganizers,
				'uid='.intval($organizerUid)
					.t3lib_pageSelect::enableFields($this->tableOrganizers),
				'',
				'',
				''
			);

			if ($dbResult && $GLOBALS['TYPO3_DB']->sql_num_rows($dbResult)) {
				$result = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult);
				$this->organizersCache[$organizerUid] =& $result;
			}
		}

		return $result;
	}

	/**
	 * Checks whether we have any organizers set, but does not check the validity of that entry.
	 *
	 * @return	boolean		true if we have any organizers asssigned to this seminar, false otherwise.
	 *
	 * @access	public
	 */
	function hasOrganizers() {
		return $this->hasRecordPropertyString('organizers');
	}

	/**
	 * Gets the URL to the detailed view of this seminar.
	 *
	 * If $this->conf['detailPID'] (and the corresponding flexforms value) is not set or 0,
	 * the link will use the current page's PID.
	 *
	 * @param	object		a plugin object (for a live page, must not be null)
	 *
	 * @return	string		URL of the seminar details page
	 *
	 * @access	public
	 */
	function getDetailedViewUrl(&$plugin) {
		return $plugin->getConfValueString('baseURL')
			.$plugin->cObj->getTypoLink_URL(
				$plugin->getConfValueInteger('detailPID'),
				array('tx_seminars_pi1[showUid]' => $this->getUid())
			);
	}

	/**
	 * Gets a plain text list of property values (if they exist),
	 * formatted as strings (and nicely lined up) in the following format:
	 *
	 * key1: value1
	 *
	 * @param	string		comma-separated list of key names
	 *
	 * @return	string		formatted output (may be empty)
	 *
	 * @access	public
	 */
	function dumpSeminarValues($keysList) {
		$keys = explode(',', $keysList);

		$maxLength = 0;
		foreach ($keys as $index => $currentKey) {
			$currentKeyTrimmed = strtolower(trim($currentKey));
			// write the trimmed key back so that we don't have to trim again
			$keys[$index] = $currentKeyTrimmed;
			$maxLength = max($maxLength, strlen($currentKeyTrimmed));
		}

		$result = '';
		foreach ($keys as $currentKey) {
			switch ($currentKey) {
				case 'date':
					$value = $this->getDate('-');
					break;
				case 'place':
					$value = $this->getPlaceShort();
					break;
				case 'price_regular':
					$value = $this->getPriceRegular(' ');
					break;
				case 'price_regular_early':
					$value = $this->getEarlyBirdPriceRegular(' ');
					break;
				case 'price_special':
					$value = $this->getPriceSpecial(' ');
					break;
				case 'price_special_early':
					$value = $this->getEarlyBirdPriceSpecial(' ');
					break;
				case 'speakers':
					$value = $this->getSpeakersShort();
					break;
				case 'time':
					$value = $this->getTime('-');
					break;
				case 'titleanddate':
					$value = $this->getTitleAndDate('-');
					break;
				case 'event_type':
					$value = $this->getEventType();
					break;
				case 'vacancies':
					$value = $this->getVacancies();
					break;
				case 'title':
					$value = $this->getTitle();
					break;
				default:
					$value = $this->getRecordPropertyString($currentKey);
					break;
			}
			$result .= str_pad($currentKey.': ', $maxLength + 2, ' ').$value.chr(10);
		}

		return $result;
	}

	/**
	 * Checks whether a certain user already is registered for this seminar.
	 *
	 * @param	integer		UID of the user to check
	 *
	 * @return	boolean		true if the user already is registered, false otherwise.
	 *
	 * @access	public
	 */
	function isUserRegistered($feUserUid) {
		$result = false;

	 	$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'COUNT(*) AS num',
			$this->tableAttendances,
			'seminar='.$this->getUid().' AND user='.$feUserUid
				.t3lib_pageSelect::enableFields($this->tableAttendances),
			'',
			'',
			'');
		if ($dbResult) {
			$numberOfRegistrations = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult);
			$result = ($numberOfRegistrations['num'] > 0);
		}

		return $result;
	}

	/**
	 * Checks whether a certain user already is registered for this seminar.
	 *
	 * @param	integer		UID of the user to check
	 *
	 * @return	string		empty string if everything is OK, else a localized error message.
	 *
	 * @access	public
	 */
	function isUserRegisteredMessage($feUserUid) {
		return ($this->isUserRegistered($feUserUid)) ? $this->pi_getLL('message_alreadyRegistered') : '';
	}

	/**
	 * Checks whether a certain user is entered as a default VIP for all events but also
	 * checks whether this user is entered as a VIP for this event,
	 * ie. he/she is allowed to view the list of registrations for this event.
	 *
	 * @param	integer		UID of the user to check
	 * @param	integer		UID of the default event VIP front-end user group
	 *
	 * @return	boolean		true if the user is a VIP for this seminar, false otherwise.
	 *
	 * @access	public
	 */
	function isUserVip($feUserUid, $defaultEventVipsFeGroupID) {
		$result = false;
		$isDefaultVip = isset($GLOBALS['TSFE']->fe_user->groupData['uid'][
				$defaultEventVipsFeGroupID
			]
		);

		if ($isDefaultVip) {
			$result = true;
		} else {
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'COUNT(*) AS num',
				$this->tableVipsMM,
				'uid_local='.$this->getUid().' AND uid_foreign='.$feUserUid,
				'',
				'',
				'');
			if ($dbResult) {
				$numberOfVips = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult);
				$result = ($numberOfVips['num'] > 0);
			}
		}

		return $result;
	}

	/**
	 * Checks whether a FE user is logged in and whether he/she may view this
	 * seminar's registrations list or see a link to it.
	 * This function can be used to check whether
	 * a) a link may be created to the page with the list of registrations
	 *    (for $whichPlugin = (seminar_list|my_events|my_vip_events))
	 * b) the user is allowed to view the list of registrations
	 *    (for $whichPlugin = (list_registrations|list_vip_registrations))
	 * c) the user is allowed to export the list of registrations as CSV
	 *    ($whichPlugin = csv_export)
	 *
	 * @param	string		the 'what_to_display' value, specifying the type of plugin: (seminar_list|my_events|my_vip_events|list_registrations|list_vip_registrations)
	 * @param	integer		the value of the registrationsListPID parameter (only relevant for (seminar_list|my_events|my_vip_events))
	 * @param	integer		the value of the registrationsVipListPID parameter (only relevant for (seminar_list|my_events|my_vip_events))
	 * @param	integer		the value of the defaultEventVipsGroupID parameter (only relevant for (list_vip_registration|my_vip_events))
	 *
	 * @return	boolean		true if a FE user is logged in and the user may view the registrations list or may see a link to that page, false otherwise.
	 *
	 * @access	public
	 */
	function canViewRegistrationsList($whichPlugin, $registrationsListPID = 0, $registrationsVipListPID = 0, $defaultEventVipsFeGroupID = 0) {
		$result = false;

		if ($this->needsRegistration() && $this->isLoggedIn()) {
			$currentUserUid = $this->getFeUserUid();
			switch ($whichPlugin) {
				case 'seminar_list':
					// In the standard list view, we could have any kind of link.
					$result = $this->canViewRegistrationsList('my_events', $registrationsListPID)
						|| $this->canViewRegistrationsList(
							'my_vip_events',
							0,
							$registrationsVipListPID,
							$defaultEventVipsFeGroupID);
					break;
				case 'my_events':
					$result = $this->isUserRegistered($currentUserUid)
						&& ((boolean) $registrationsListPID);
					break;
				case 'my_vip_events':
					$result = $this->isUserVip($currentUserUid, $defaultEventVipsFeGroupID)
						&& ((boolean) $registrationsVipListPID);
					break;
				case 'list_registrations':
					$result = $this->isUserRegistered($currentUserUid);
					break;
				case 'list_vip_registrations':
					$result = $this->isUserVip(
						$currentUserUid, $defaultEventVipsFeGroupID
					);
					break;
				case 'csv_export':
					$result = $this->isUserVip(
						$currentUserUid, $defaultEventVipsFeGroupID
					) && $this->getConfValueBoolean('allowCsvExportForVips');
					break;
				default:
					// For all other plugins, we don't grant access.
					break;
			}
		}

		return $result;
	}

	/**
	 * Checks whether a FE user is logged in and whether he/she may view this
	 * seminar's registrations list.
	 * This function is intended to be used from the registrations list,
	 * NOT to check whether a link to that list should be shown.
	 *
	 * @param	string		the 'what_to_display' value, specifying the type of plugin: (list_registrations|list_vip_registrations)
	 *
	 * @return	string		empty string if everything is OK, otherwise a localized error message
	 *
	 * @access	public
	 */
	function canViewRegistrationsListMessage($whichPlugin) {
		$result = '';

		if (!$this->needsRegistration()) {
			$result = $this->pi_getLL('message_noRegistrationNecessary');
		} elseif (!$this->isLoggedIn()) {
			$result = $this->pi_getLL('message_notLoggedIn');
		} elseif (!$this->canViewRegistrationsList($whichPlugin)) {
			$result = $this->pi_getLL('message_accessDenied');
		}

		return $result;
	}

	/**
	 * Checks whether it is possible at all to register for this seminar,
	 * ie. it needs registration at all,
	 *     has not been canceled,
	 *     has a date set,
	 *     has not begun yet,
	 *     the registration deadline is not over yet,
	 *     and there are still vacancies.
	 *
	 * @return	boolean		true if registration is possible, false otherwise.
	 *
	 * @access	public
	 */
	function canSomebodyRegister() {
		return $this->needsRegistration() &&
			!$this->isCanceled() &&
			$this->hasDate() &&
			!$this->isRegistrationDeadlineOver() &&
			$this->hasVacancies();
	}

	/**
	 * Checks whether it is possible at all to register for this seminar,
	 * ie. it needs registration at all,
	 *     has not been canceled,
	 *     has a date set,
	 *     has not begun yet,
	 *     the registration deadline is not over yet
	 *     and there are still vacancies,
	 * and returns a localized error message if registration is not possible.
	 *
	 * @return	string		empty string if everything is OK, else a localized error message.
	 *
	 * @access	public
	 */
	function canSomebodyRegisterMessage() {
		$message = '';

		if (!$this->needsRegistration()) {
			$message = $this->pi_getLL('message_noRegistrationNecessary');
		} elseif ($this->isCanceled()) {
			$message = $this->pi_getLL('message_seminarCancelled');
		} elseif (!$this->hasDate()) {
			$message = $this->pi_getLL('message_noDate');
		} elseif ($this->isRegistrationDeadlineOver()) {
			$message = $this->pi_getLL('message_seminarRegistrationIsClosed');
		} elseif ($this->isFull()) {
			$message = $this->pi_getLL('message_noVacancies');
		}

		return $message;
	}

	/**
	 * Checks whether this event has been canceled.
	 *
	 * @return	boolean		true if the event has been canceled, false otherwise
	 *
	 * @access	public
	 */
	function isCanceled() {
		return $this->getRecordPropertyBoolean('cancelled');
	}

 	/**
	 * Checks whether the latest possibility to register for this event is over.
	 *
	 * The latest moment is either the time the event starts, or a set registration deadline.
	 *
	 * @return	boolean		true if the deadline has passed, false otherwise
	 *
	 * @access	public
	 */
	function isRegistrationDeadlineOver() {
		return ($GLOBALS['SIM_EXEC_TIME'] >= $this->getLatestPossibleRegistrationTime());
	}

 	/**
	 * Checks whether the latest possibility to register with early bird rebate for this event is over.
	 *
	 * The latest moment is just before a set early bird deadline.
	 *
	 * @return	boolean		true if the deadline has passed, false otherwise
	 *
	 * @access	protected
	 */
	function isEarlyBirdDeadlineOver() {
		return ($GLOBALS['SIM_EXEC_TIME'] >= $this->getLatestPossibleEarlyBirdRegistrationTime());
	}

	/**
	 * Checks whether for this event, registration is necessary at all.
	 *
	 * @return	boolean		true if registration is necessary, false otherwise
	 *
	 * @access	public
	 */
	function needsRegistration() {
		return $this->getRecordPropertyBoolean('needs_registration');
	}

	/**
	 * Checks whether this event allows multiple registrations by the same
	 * FE user.
	 *
	 * @return	boolean		true if multiple registrations are allowed, false otherwise
	 *
	 * @access	public
	 */
	function allowsMultipleRegistrations() {
		return $this->getRecordPropertyBoolean('allows_multiple_registrations');
	}

	/**
	 * Recalculates the statistics for this seminar:
	 *   the number of participants,
	 *   whether there are enough registrations for this seminar to take place,
	 *   and whether this seminar even is full.
	 *
	 * @access	public
	 */
	function updateStatistics() {
		$numberOfAttendances = $this->countAttendances();
		$numberOfAttendancesPaid = $this->countAttendances('(paid=1 OR datepaid!=0)');

		// We count paid and unpaid registrations.
		// This behaviour will be configurable in a later version.
		$this->recordData['attendees'] = $numberOfAttendances;
		// Let's store the other result in case someone needs it.
		$this->numberOfAttendancesPaid = $numberOfAttendancesPaid;

		// We use 1 and 0 instead of boolean values as we need to write a number into the DB
		$this->recordData['enough_attendees'] = ($this->getAttendances() >= $this->getRecordPropertyInteger('attendees_min')) ? 1 : 0;
		// We use 1 and 0 instead of boolean values as we need to write a number into the DB
		$this->recordData['is_full'] = ($this->getAttendances() >= $this->getRecordPropertyInteger('attendees_max')) ? 1 : 0;

		$result = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			$this->tableSeminars,
			'uid='.$this->getUid(),
			array(
				'attendees' => $this->getRecordPropertyInteger('attendees'),
				'enough_attendees' => $this->getRecordPropertyInteger('enough_attendees'),
				'is_full' => $this->getRecordPropertyInteger('is_full'),
				'tstamp' => time()
			)
		);

		return;
	}

	/**
	 * Queries the DB for the number of visible attendances for this event
	 * and returns the result of the DB query with the number stored in 'num'
	 * (the result will be null if the query fails).
	 *
	 * This function takes multi-seat registrations into account as well.
	 *
	 * An additional string can be added to the WHERE clause to look only for
	 * certain attendances, e.g. only the paid ones.
	 *
	 * Note that this does not write the values back to the seminar record yet.
	 * This needs to be done in an additional step after this.
	 *
	 * @param	string		string that will be prepended to the WHERE clause
	 *						using AND, e.g. 'pid=42' (the AND and the enclosing
	 *						spaces are not necessary for this parameter)
	 *
	 * @return	integer		the number of attendances
	 *
	 * @access	protected
	 */
	function countAttendances($queryParameters = '1') {
		$result = 0;

		$dbResultSingleSeats = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'COUNT(*) AS number',
			$this->tableAttendances,
			$queryParameters
				.' AND seminar='.$this->getUid()
				.' AND seats=0'
				.t3lib_pageSelect::enableFields($this->tableAttendances),
			'',
			'',
			''
		);
		if ($dbResultSingleSeats) {
			$fieldsSingleSeats = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResultSingleSeats);
			$result += $fieldsSingleSeats['number'];
		}

		$dbResultMultiSeats = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'SUM(seats) AS number',
			$this->tableAttendances,
			$queryParameters
				.' AND seminar='.$this->getUid()
				.' AND seats!=0'
				.t3lib_pageSelect::enableFields($this->tableAttendances),
			'',
			'',
			''
		);

		if ($dbResultMultiSeats) {
			$fieldsMultiSeats = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResultMultiSeats);
			$result += $fieldsMultiSeats['number'];
		}

		return $result;
	}

	/**
	 * Retrieves the topic from the DB and returns it as an object.
	 *
	 * In case of an error, the return value will be null.
	 *
	 * @return	object		a reference to the topic object (will be null if an error has occured)
	 *
	 * @access	private
	 */
	function &retrieveTopic() {
		$result = null;

		// Check whether this event has an topic set.
		if ($this->hasRecordPropertyInteger('topic')) {
			if (tx_seminars_objectfromdb::recordExists($this->getRecordPropertyInteger('topic'), $this->tableSeminars)) {
			/** Name of the seminar class in case someone subclasses it. */
				$seminarClassname = t3lib_div::makeInstanceClassName('tx_seminars_seminar');
				$result =& new $seminarClassname($this->getRecordPropertyInteger('topic'));
			}
		}
		return $result;
	}

	/**
	 * Checks whether we are a date record.
	 *
	 * @return	boolean		true if we are a date record, false otherwise.
	 *
	 * @access	public
	 */
	function isEventDate() {
		return ($this->getRecordPropertyInteger('object_type') == 2);
	}

	/**
	 * Checks whether we are a date record and have a topic.
	 *
	 * @return	boolean		true if we are a date record and have a topic, false otherwise.
	 *
	 * @access	public
	 */
	function isTopicOkay() {
		return ($this->isEventDate() && $this->topic && $this->topic->isOk());
	}

	/**
	 * Gets the uid of the topic record if we are a date record.
	 * Otherwise the uid of this record is returned.
	 *
	 * @return	integer		the uid of this or its topic record
	 *
	 * @access	public
	 */
	function getTopicUid() {
		if ($this->isTopicOkay()) {
			return $this->topic->getUid();
		} else {
			return $this->getUid();
		}
	}

	/**
	 * Checks a integer element of the record data array for existence and non-emptiness.
	 * If we are a date record, it'll be retrieved from the corresponding topic record.
	 *
	 * @param	string		key of the element to check
	 *
	 * @return	boolean		true if the corresponding integer exists and is non-empty
	 *
	 * @access	private
	 */
	function hasTopicInteger($key) {
		$result = false;

		if ($this->isTopicOkay()) {
			$result = $this->topic->hasRecordPropertyInteger($key);
		} else {
			$result = $this->hasRecordPropertyInteger($key);
		}

		return $result;
	}

	/**
	 * Gets an (intval'ed) integer element of the record data array.
	 * If the array has not been initialized properly, 0 is returned instead.
	 * If we are a date record, it'll be retrieved from the corresponding topic record.
	 *
	 * @param	string		the integer field
	 *
	 * @return	integer		the corresponding element from the record data array
	 *
	 * @access	private
	 */
	function getTopicInteger($key) {
		$result = 0;

		if ($this->isTopicOkay()) {
			$result = $this->topic->getRecordPropertyInteger($key);
		} else {
			$result = $this->getRecordPropertyInteger($key);
		}

		return $result;
	}

	/**
	 * Checks a string element of the record data array for existence and non-emptiness.
	 * If we are a date record, it'll be retrieved from the corresponding topic record.
	 *
	 * @param	string		key of the element to check
	 *
	 * @return	boolean		true if the corresponding string exists and is non-empty
	 *
	 * @access	private
	 */
	function hasTopicString($key) {
		$result = false;

		if ($this->isTopicOkay()) {
			$result = $this->topic->hasRecordPropertyString($key);
		} else {
			$result = $this->hasRecordPropertyString($key);
		}

		return $result;
	}

	/**
	 * Gets a trimmed string element of the record data array.
	 * If the array has not been initialized properly, an empty string is returned instead.
	 * If we are a date record, it'll be retrieved from the corresponding topic record.
	 *
	 * @param	string		the string field
	 *
	 * @return	string		the corresponding element from the record data array
	 *
	 * @access	private
	 */
	function getTopicString($key) {
		$result = '';

		if ($this->isTopicOkay()) {
			$result = $this->topic->getRecordPropertyString($key);
		} else {
			$result = $this->getRecordPropertyString($key);
		}

		return $result;
	}

	/**
	 * Checks a decimal element of the record data array for existence and a value != 0.00.
	 * If we are a date record, it'll be retrieved from the corresponding topic record.
	 *
	 * @param	string		key of the element to check
	 *
	 * @return	boolean		true if the corresponding decimal value exists and is not 0.00
	 *
	 * @access	private
	 */
	function hasTopicDecimal($key) {
		$result = false;

		if ($this->isTopicOkay()) {
			$result = $this->topic->hasRecordPropertyDecimal($key);
		} else {
			$result = $this->hasRecordPropertyDecimal($key);
		}

		return $result;
	}

	/**
	 * Gets a decimal element of the record data array.
	 * If the array has not been initialized properly, an empty string is returned instead.
	 * If we are a date record, it'll be retrieved from the corresponding topic record.
	 *
	 * @param	string		the name of the field to retrieve
	 *
	 * @return	string		the corresponding element from the record data array
	 *
	 * @access	private
	 */
	function getTopicDecimal($key) {
		$result = '';

		if ($this->isTopicOkay()) {
			$result = $this->topic->getRecordPropertyDecimal($key);
		} else {
			$result = $this->getRecordPropertyDecimal($key);
		}

		return $result;
	}

	/**
	 * Checks whether we have any option checkboxes. If we are a date record,
	 * the corresponding topic record will be checked.
	 *
	 * @return	boolean		true if we have at least one option checkbox, false otherwise
	 *
	 * @access	public
	 */
	function hasCheckboxes() {
		return $this->hasTopicInteger('checkboxes');
	}

	/**
	 * Gets the option checkboxes associated with this event. If we are a date
	 * record, the option checkboxes of the corresponding topic record will be
	 * retrieved.
	 *
	 * @return	array		an array of option checkboxes, consisting each of a nested array with the keys "caption" (for the title) and "value" (for the uid)
	 *
	 * @access	public
	 */
	function getCheckboxes() {
		$result = array();
		$where = 'EXISTS (SELECT * FROM '.$this->tableSeminarsCheckboxesMM
					.' WHERE '.$this->tableSeminarsCheckboxesMM.'.uid_local='
					.$this->getTopicInteger('uid').' AND '
					.$this->tableSeminarsCheckboxesMM.'.uid_foreign='
					.$this->tableCheckboxes.'.uid)'
					.t3lib_pageSelect::enableFields($this->tableCheckboxes);

		if ($this->hasCheckboxes()) {
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'uid, title, sorting',
				$this->tableCheckboxes.', '.$this->tableSeminarsCheckboxesMM,
				'uid_local='.$this->getTopicInteger('uid').' AND uid_foreign=uid'
					.t3lib_pageSelect::enableFields($this->tableCheckboxes),
				'',
				'sorting'
			);

			if ($dbResult) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult)) {
					$result[] = array(
						'caption' => $row['title'],
						'value'   => $row['uid']
					);
				}
			}

		}

		return $result;
	}

	/**
	 * Gets the PID of the system folder where the registration records of this
	 * event should be stored. If no folder is set in this event's topmost
	 * organizer record (ie. the page configured in
	 * plugin.tx_seminars.attendancesPID should be used), this function will
	 * return 0.
	 *
	 * @return	integer		the PID of the systen folder where registration records for this event should be stored (or 0 if no folder is set)
	 *
	 * @access	public
	 */
	function getAttendancesPid() {
		$result = 0;

		if ($this->hasOrganizers()) {
			$organizerUids = explode(',', $this->getRecordPropertyString('organizers'));
			$firstOrganizerData =& $this->retrieveOrganizer($organizerUids[0]);
			$result = $firstOrganizerData['attendances_pid'];
		}

		return $result;
	}

	/**
	 * Checks whether this event's topmost organizer has a PID set to store the
	 * registration records in.
	 *
	 * @return	boolean		true if a the systen folder for registration records is specified in this event's topmost organizers record, false otherwise
	 *
	 * @access	public
	 */
	function hasAttendancesPid() {
		return (boolean) $this->getAttendancesPid();
	}

	/**
	 * Checks whether the logged-in FE user is the owner of this event.
	 *
	 * @return	boolean		true if a FE user is logged in and the user is the owner of this event, false otherwise
	 *
	 * @access	public
	 */
	function isOwnerFeUser() {
		return $this->hasRecordPropertyInteger('owner_feuser')
			&& ($this->getRecordPropertyInteger('owner_feuser')
				== $this->getFeUserUid());
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/class.tx_seminars_seminar.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/class.tx_seminars_seminar.php']);
}

?>
