<?php

use OliverKlee\Seminars\BackEnd\CancelEventMailForm;
use OliverKlee\Seminars\Tests\Unit\Support\Traits\BackEndTestsTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Test case.
 *
 * @author Mario Rimann <mario@screenteam.com>
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class Tx_Seminars_Tests_Unit_BackEnd_CancelEventMailFormTest extends \Tx_Phpunit_TestCase
{
    use BackEndTestsTrait;

    /**
     * @var CancelEventMailForm
     */
    private $fixture;

    /**
     * @var \Tx_Oelib_TestingFramework
     */
    private $testingFramework;

    /**
     * UID of a dummy system folder
     *
     * @var int
     */
    private $dummySysFolderUid;

    /**
     * UID of a dummy organizer record
     *
     * @var int
     */
    private $organizerUid;

    /**
     * UID of a dummy event record
     *
     * @var int
     */
    private $eventUid;

    /**
     * @var \Tx_Oelib_EmailCollector
     */
    protected $mailer = null;

    protected function setUp()
    {
        $this->unifyTestingEnvironment();

        /** @var \Tx_Oelib_MailerFactory $mailerFactory */
        $mailerFactory = GeneralUtility::makeInstance(\Tx_Oelib_MailerFactory::class);
        $mailerFactory->enableTestMode();
        $this->mailer = $mailerFactory->getMailer();

        $this->testingFramework = new \Tx_Oelib_TestingFramework('tx_seminars');
        \Tx_Oelib_MapperRegistry::getInstance()->activateTestingMode($this->testingFramework);

        $this->dummySysFolderUid = $this->testingFramework->createSystemFolder();
        \Tx_Oelib_PageFinder::getInstance()->setPageUid($this->dummySysFolderUid);

        $this->organizerUid = $this->testingFramework->createRecord(
            'tx_seminars_organizers',
            [
                'title' => 'Dummy Organizer',
                'email' => 'foo@example.org',
            ]
        );
        $this->eventUid = $this->testingFramework->createRecord(
            'tx_seminars_seminars',
            [
                'pid' => $this->dummySysFolderUid,
                'title' => 'Dummy event',
                'object_type' => \Tx_Seminars_Model_Event::TYPE_DATE,
                'begin_date' => $GLOBALS['SIM_EXEC_TIME'] + 86400,
                'organizers' => 0,
            ]
        );
        $this->testingFramework->createRelationAndUpdateCounter(
            'tx_seminars_seminars',
            $this->eventUid,
            $this->organizerUid,
            'organizers'
        );

        $this->fixture = new CancelEventMailForm($this->eventUid);
    }

    protected function tearDown()
    {
        $this->testingFramework->cleanUp();
        $this->restoreOriginalEnvironment();
    }

    ///////////////////////////////////////////////
    // Tests regarding the rendering of the form.
    ///////////////////////////////////////////////

    /**
     * @test
     */
    public function renderContainsSubmitButton()
    {
        self::assertContains(
            '<button class="submitButton cancelEvent"><p>' .
            $GLOBALS['LANG']->getLL('cancelMailForm_sendButton') .
            '</p></button>',
            $this->fixture->render()
        );
    }

    /**
     * @test
     */
    public function renderContainsPrefilledBodyFieldWithLocalizedSalutation()
    {
        self::assertContains('salutation', $this->fixture->render());
    }

    /**
     * @test
     */
    public function renderContainsTheCancelEventActionForThisForm()
    {
        self::assertContains(
            '<input type="hidden" name="action" value="cancelEvent" />',
            $this->fixture->render()
        );
    }

    ////////////////////////////////
    // Tests for the localization.
    ////////////////////////////////

    /**
     * @test
     */
    public function localizationReturnsLocalizedStringForExistingKey()
    {
        self::assertEquals(
            'Events',
            $GLOBALS['LANG']->getLL('title')
        );
    }

    /*
     * Tests for setEventStatus
     */

    /**
     * @test
     */
    public function setEventStatusSetsStatusToCanceled()
    {
        $this->fixture->setPostData(
            [
                'action' => 'cancelEvent',
                'isSubmitted' => '1',
                'subject' => 'foo',
                'messageBody' => 'foo bar',
            ]
        );
        $this->fixture->render();

        self::assertTrue(
            $this->testingFramework->existsRecord(
                'tx_seminars_seminars',
                'uid = ' . $this->eventUid . ' AND cancelled = ' .
                    \Tx_Seminars_Model_Event::STATUS_CANCELED
            )
        );
    }

    /**
     * @test
     */
    public function setEventStatusCreatesFlashMessage()
    {
        $this->mockBackEndUser->expects(self::atLeastOnce())->method('setAndSaveSessionData')
            ->with(self::anything(), self::anything());

        $this->fixture->setPostData(
            [
                'action' => 'cancelEvent',
                'isSubmitted' => '1',
                'subject' => 'foo',
                'messageBody' => 'foo bar',
            ]
        );
        $this->fixture->render();
    }

    /////////////////////////////////
    // Tests concerning the e-mails
    /////////////////////////////////

    /**
     * @test
     */
    public function sendEmailToAttendeesSendsEmailWithNameOfRegisteredUserOnSubmitOfValidForm()
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'pid' => $this->dummySysFolderUid,
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(
                    '',
                    ['email' => 'foo@example.com', 'name' => 'foo User']
                ),
            ]
        );

        $messageBody = '%salutation' . $GLOBALS['LANG']->getLL('confirmMailForm_prefillField_messageBody');
        $this->fixture->setPostData(
            [
                'action' => 'confirmEvent',
                'isSubmitted' => '1',
                'subject' => 'foo',
                'messageBody' => $messageBody,
            ]
        );
        $this->fixture->render();

        self::assertContains(
            'foo User',
            $this->mailer->getFirstSentEmail()->getBody()
        );
    }

    /**
     * @test
     */
    public function sendEmailCallsHookWithRegistration()
    {
        $registrationUid = $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'pid' => $this->dummySysFolderUid,
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(
                    '',
                    ['email' => 'foo@example.com', 'name' => 'foo User']
                ),
            ]
        );

        /** @var \Tx_Seminars_Model_Registration $registration */
        $registration = \Tx_Oelib_MapperRegistry::get(\Tx_Seminars_Mapper_Registration::class)->find($registrationUid);
        $hook = $this->getMock(\Tx_Seminars_Interface_Hook_BackEndModule::class);
        $hook->expects(self::once())->method('modifyCancelEmail')
            ->with($registration, self::anything());

        $hookClass = get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars']['backEndModule'][$hookClass] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->fixture->setPostData(
            [
                'action' => 'confirmEvent',
                'isSubmitted' => '1',
                'subject' => 'foo',
                'messageBody' => 'some message body',
            ]
        );
        $this->fixture->render();
    }

    /**
     * @test
     */
    public function sendEmailForTwoRegistrationsCallsHookTwice()
    {
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'pid' => $this->dummySysFolderUid,
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(
                    '',
                    ['email' => 'foo@example.com', 'name' => 'foo User']
                ),
            ]
        );
        $this->testingFramework->createRecord(
            'tx_seminars_attendances',
            [
                'pid' => $this->dummySysFolderUid,
                'seminar' => $this->eventUid,
                'user' => $this->testingFramework->createFrontEndUser(
                    '',
                    ['email' => 'bar@example.com', 'name' => 'foo User']
                ),
            ]
        );

        $hook = $this->getMock(\Tx_Seminars_Interface_Hook_BackEndModule::class);
        $hook->expects(self::exactly(2))->method('modifyCancelEmail');

        $hookClass = get_class($hook);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['seminars']['backEndModule'][$hookClass] = $hookClass;
        GeneralUtility::addInstance($hookClass, $hook);

        $this->fixture->setPostData(
            [
                'action' => 'confirmEvent',
                'isSubmitted' => '1',
                'subject' => 'foo',
                'messageBody' => 'some message body',
            ]
        );
        $this->fixture->render();
    }
}
