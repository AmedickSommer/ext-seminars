<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Test case.
 *
 *
 * @author Bernd Schönbach <bernd@oliverklee.de>
 */
class Tx_Seminars_Tests_Unit_Mapper_FrontEndUserTest extends Tx_Phpunit_TestCase
{
    /**
     * @var Tx_Seminars_Mapper_FrontEndUser the object to test
     */
    private $fixture;

    protected function setUp()
    {
        $this->testingFramework = new Tx_Oelib_TestingFramework('tx_seminars');

        $this->fixture = Tx_Oelib_MapperRegistry::get(
            Tx_Seminars_Mapper_FrontEndUser::class
        );
    }

    protected function tearDown()
    {
        $this->testingFramework->cleanUp();
    }

    //////////////////////////////////////
    // Tests for the basic functionality
    //////////////////////////////////////

    /**
     * @test
     */
    public function mapperForGhostReturnsSeminarsFrontEndUserInstance()
    {
        self::assertInstanceOf(Tx_Seminars_Model_FrontEndUser::class, $this->fixture->getNewGhost());
    }

    ///////////////////////////////////
    // Tests concerning the relations
    ///////////////////////////////////

    /**
     * @test
     */
    public function relationToRegistrationIsReadFromRegistrationMapper()
    {
        $registration = Tx_Oelib_MapperRegistry
            ::get(Tx_Seminars_Mapper_Registration::class)->getNewGhost();

        $model = $this->fixture->getLoadedTestingModel(
            array('tx_seminars_registration' => $registration->getUid())
        );

        self::assertSame(
            $registration,
            $model->getRegistration()
        );
    }
}
