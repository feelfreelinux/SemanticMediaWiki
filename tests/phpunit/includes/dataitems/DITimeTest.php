<?php

namespace SMW\Tests;

use SMWDITime as DITime;

/**
 * @covers \SMWDITime
 *
 * @license GNU GPL v2+
 * @since 2.4
 *
 * @author mwjames
 */
class DITimeTest extends \PHPUnit_Framework_TestCase {

	public function testCanConstruct() {

		$this->assertInstanceOf(
			'\SMWDITime',
			new DITime(  DITime::CM_GREGORIAN, 1970 )
		);
	}

	public function testNewFromTimestamp() {

		$instance = DITime::newFromTimestamp( '1362200400' );

		$this->assertInstanceOf(
			'\SMWDITime',
			$instance
		);
	}

	public function testNewFromDateTime() {

		$instance = DITime::newFromDateTime(
			new \DateTime( '2012-07-08 11:14:15.638276' )
		);

		$this->assertEquals(
			'15.638276',
			$instance->getSecond()
		);

		$instance = DITime::newFromDateTime(
			new \DateTime( '1582-10-04' )
		);

		$this->assertEquals(
			DITime::CM_JULIAN,
			$instance->getCalendarModel()
		);

		$instance = DITime::newFromDateTime(
			new \DateTime( '1582-10-05' )
		);

		$this->assertEquals(
			DITime::CM_GREGORIAN,
			$instance->getCalendarModel()
		);
	}

	public function testDateTimeRoundTrip() {

		$dateTime = new \DateTime( '2012-07-08 11:14:15.638276' );

		$instance = DITime::newFromDateTime(
			$dateTime
		);

		$this->assertEquals(
			$dateTime,
			$instance->asDateTime()
		);
	}

	public function testDateTimeWithLargeMs() {

		$dateTime = new \DateTime( '1300-11-02 12:03:25.888500' );

		$instance = new DITime(
			2, 1300, 11, 02, 12, 03, 25.888499949
		);

		$this->assertEquals(
			$dateTime,
			$instance->asDateTime()
		);
	}

	public function testDateTimeWithHistoricDate() {

		$dateTime = new \DateTime( '-0900-02-02 00:00:00' );

		$instance = new DITime(
			2, -900, 02, 02
		);

		$this->assertEquals(
			$dateTime,
			$instance->asDateTime()
		);
	}

}
