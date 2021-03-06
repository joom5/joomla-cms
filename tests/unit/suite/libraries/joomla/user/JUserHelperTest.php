<?php
require_once 'PHPUnit/Framework.php';

require_once JPATH_BASE . '/libraries/joomla/user/helper.php';

/**
 * Test class for JUserHelper.
 * Generated by PHPUnit on 2009-10-26 at 22:44:33.
 */
class JUserHelperTest extends JoomlaDatabaseTestCase
{
	/**
	 * @var JUserHelper
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp()
	{
		parent::setUp();

		$this->saveFactoryState();

		$this->object = new JUserHelper;
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown()
	{
		$this->setErrorhandlers($this->savedErrorState);
	}

	/**
    * Test cases for userGroups
    *
    * Each test case provides
    * - integer  userid  a user id
    * - array    group   user group, given as hash
    *                    group_id => group_name,
    *                    empty if undefined
    * - array    error   error info, given as hash
    *                    with indices 'code', 'msg', and
    *                    'info', empty, if no error occured
    * @see ... (link to where the group and error structures are
    *      defined)
    * @return array
    */
   function casesGetUserGroups()
   {
       return array(
           'validSuperUser' => array(
               42,
               array( 'Super Users' => 8 ),
               array(),
           ),
       );
   }

	/**
	 * TestingGetUserGroups().
	 *
	 * @param	integer	User ID
	 * @param	mixed	User object or empty array if unknown
	 * @param	array	Expected error info
	 *
	 * @return void
	 * @dataProvider casesGetUserGroups
	 */
	public function testGetUserGroups( $userid, $expected, $error )
	{
		$this->assertThat(
			JUserHelper::getUserGroups($userid),
			$this->equalTo($expected)
		);
		$this->assertThat(
			JUserHelperTest::$actualError,
			$this->equalTo($error)
		);
	}
}

