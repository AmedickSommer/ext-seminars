<?php
/***************************************************************
* Copyright notice
*
* (c) 2009 Niels Pardon (mail@niels-pardon.de)
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

if (t3lib_extMgm::isLoaded('oelib')) {
	require_once(t3lib_extMgm::extPath('oelib') . 'class.tx_oelib_Autoloader.php');
}
if (t3lib_extMgm::isLoaded('seminars')) {
	require_once(t3lib_extMgm::extPath('seminars') . 'lib/tx_seminars_constants.php');
}

/**
 * Class 'ext_update' for the 'seminars' extension.
 *
 * This class offers functions to update the database from one version to another.
 *
 * @package TYPO3
 * @subpackage tx_seminars
 *
 * @author Niels Pardon <mail@niels-pardon.de>
 */
class ext_update {
	/**
	 * Returns the update module content.
	 *
	 * @return string the update module content, will be empty if nothing was
	 *                updated
	 */
	public function main() {
		$result = '';
		try {
			if ($this->needsToUpdateEventOrganizerRelations()) {
				$result .= $this->updateEventOrganizerRelations();
			}
			if ($this->needsToUpdateNeedsRegistrationField()) {
				$result .= $this->updateNeedsRegistrationField();
			}
		} catch (tx_oelib_Exception_Database $exception) {
			$result = '';
		}

		return $result;
	}

	/**
	 * Returns whether the update module may be accessed.
	 *
	 * @return boolean true if the update module may be accessed, false otherwise
	 */
	public function access() {
		if (!t3lib_extMgm::isLoaded('oelib')
			|| !t3lib_extMgm::isLoaded('seminars')
		) {
			return false;
		}
		if (!tx_oelib_db::existsTable(SEMINARS_TABLE_SEMINARS_ORGANIZERS_MM)
			|| !tx_oelib_db::existsTable(SEMINARS_TABLE_SEMINARS)
		) {
			return false;
		}
		if (!tx_oelib_db::tableHasColumn(
			SEMINARS_TABLE_SEMINARS, 'needs_registration'
		)) {
			return false;
		}

		try {
			$result = (($this->needsToUpdateEventOrganizerRelations()
				&& $this->hasEventsWithOrganizers())
				|| $this->needsToUpdateNeedsRegistrationField()
			);
		} catch (tx_oelib_Exception_Database $exception) {
			$result = false;
		}

		return $result;
	}

	/**
	 * Updates the event-organizer-relations to real M:M relations.
	 *
	 * @return string information about the status of the update process,
	 *                will not be empty.
	 */
	private function updateEventOrganizerRelations() {
		$result = '<h2>Updating event-organizer-relations:</h2>';
		$result .= '<ul>';

		// Gets all events which have an organizer set.
		$eventsWithOrganizers = tx_oelib_db::selectMultiple(
			'uid, title, organizers',
			SEMINARS_TABLE_SEMINARS,
			SEMINARS_TABLE_SEMINARS . '.organizers <> 0'
		);

		foreach ($eventsWithOrganizers as $event) {
			$result .= '<li>Event #' . $event['uid'];

			// Adds a relation entry for each organizer UID.
			$result .= '<ul>';
			$sorting = 0;
			$organizerUids = t3lib_div::trimExplode(
				',', $event['organizers'], true
			);
			foreach ($organizerUids as $organizerUid) {
				$result .= '<li>Organizer #' . $organizerUid . '</li>';
				tx_oelib_db::insert(
					SEMINARS_TABLE_SEMINARS_ORGANIZERS_MM,
					array(
						'uid_local' => $event['uid'],
						'uid_foreign' => intval($organizerUid),
						'sorting' => $sorting,
					)
				);
				$sorting++;
			}
			$result .= '</ul>';

			// Updates the event's organizers field with the number of organizer
			// UIDs.
			tx_oelib_db::update(
				SEMINARS_TABLE_SEMINARS,
				SEMINARS_TABLE_SEMINARS . '.uid = ' . $event['uid'],
				array('organizers' => count($organizerUids))
			);

			$result .= '</li>';
		}

		$result .= '</ul>';

		return $result;
	}

	/**
	 * Checks whether there are no real event-organizer-m:n-relations yet.
	 *
	 * @return boolean true if there are no real event-organizer-m:n-relations,
	 *                 false otherwise
	 */
	private function needsToUpdateEventOrganizerRelations() {
		$row = tx_oelib_db::selectSingle(
			'COUNT(*) AS count', SEMINARS_TABLE_SEMINARS_ORGANIZERS_MM, '1=1'
		);

		return ($row['count'] == 0);
	}

	/**
	 * Checks whether there are any events with organizers set.
	 *
	 * @return boolean true if there is at least one event with organizers set,
	 *                 false otherwise
	 */
	private function hasEventsWithOrganizers() {
		$row = tx_oelib_db::selectSingle(
			'COUNT(*) AS count',
			SEMINARS_TABLE_SEMINARS,
			SEMINARS_TABLE_SEMINARS . '.organizers<>0'
		);

		return ($row['count'] > 0);
	}

	/**
	 * Checks whether there are events with attendees_max > 0 and
	 * needs_registration = 0.
	 *
	 * @return boolean true if any rows need to be updated, false otherwise
	 */
	private function needsToUpdateNeedsRegistrationField() {
		$row = tx_oelib_db::selectSingle(
			'COUNT(*) AS number', SEMINARS_TABLE_SEMINARS,
			'attendees_max > 0 AND needs_registration = 0 '
		);

		return ($row['number'] > 0);
	}

	/**
	 * Updates the needs_registration field of the event records.
	 *
	 * @return string information about the status of the update process,
	 *                will not be empty
	 */
	private function updateNeedsRegistrationField() {
		return '<h2>Updating events needs_registrations field.</h2>' .
			'<p> Updating ' . tx_oelib_db::update(
				SEMINARS_TABLE_SEMINARS,
				'needs_registration = 0 AND attendees_max > 0',
				array('needs_registration' => 1)
			) . ' events.</p>';
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/class.ext_update.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/seminars/class.ext_update.php']);
}
?>