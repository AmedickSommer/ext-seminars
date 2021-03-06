<?php
defined('TYPO3_MODE') or die();

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_timeslots',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'hideTable' => true,
        'iconfile' => 'EXT:seminars/Resources/Public/Icons/TimeSlot.gif',
        'searchFields' => 'title',
    ],
    'interface' => [
        'showRecordFieldList' => 'begin_date, end_date, entry_date, speakers, place, room',
    ],
    'columns' => [
        'seminar' => [
            'config' => [
                'type' => 'input',
                'size' => 30,
            ],
        ],
        'title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_timeslots.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'begin_date' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_timeslots.begin_date',
            'config' => [
                'type' => 'input',
                'size' => 12,
                'eval' => 'datetime, required',
                'checkbox' => '0',
                'default' => '0',
            ],
        ],
        'end_date' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_timeslots.end_date',
            'config' => [
                'type' => 'input',
                'size' => 12,
                'eval' => 'datetime',
                'checkbox' => '0',
                'default' => '0',
            ],
        ],
        'entry_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_timeslots.entry_date',
            'config' => [
                'type' => 'input',
                'size' => 12,
                'eval' => 'datetime',
                'checkbox' => '0',
                'default' => '0',
            ],
        ],
        'speakers' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_timeslots.speakers',
            'config' => [
                'type' => \OliverKlee\Seminars\BackEnd\TceForms::getSelectType(),
                'renderType' => 'selectMultipleSideBySide',
                'internal_type' => 'db',
                'allowed' => 'tx_seminars_speakers',
                'foreign_table' => 'tx_seminars_speakers',
                'foreign_table_where' => 'ORDER BY title',
                'size' => 10,
                'minitems' => 0,
                'maxitems' => 999,
                'MM' => 'tx_seminars_timeslots_speakers_mm',
            ],
        ],
        'place' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_timeslots.place',
            'config' => [
                'type' => \OliverKlee\Seminars\BackEnd\TceForms::getSelectType(),
                'renderType' => 'selectSingle',
                'internal_type' => 'db',
                'allowed' => 'tx_seminars_sites',
                'foreign_table' => 'tx_seminars_sites',
                'foreign_table_where' => 'ORDER BY title',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'items' => [['', '0']],
            ],
        ],
        'room' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:seminars/Resources/Private/Language/locallang_db.xlf:tx_seminars_timeslots.room',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'begin_date, end_date, entry_date, speakers, place, room'],
    ],
];

if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 8006000) {
    $tca['columns']['begin_date']['config']['renderType'] = 'inputDateTime';
    $tca['columns']['end_date']['config']['renderType'] = 'inputDateTime';
    $tca['columns']['entry_date']['config']['renderType'] = 'inputDateTime';
}

return $tca;
