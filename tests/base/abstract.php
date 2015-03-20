<?php
/**
 * @author: KentProjects <developer@kentprojects.com>
 * @license: Copyright KentProjects
 * @link: http://kentprojects.com
 */
abstract class KentProjects_TestBase extends PHPUnit_Framework_TestCase
{
	/**
	 * @param Model $expected
	 * @param Model $actual
	 * @param string $message
	 * @return void
	 */
	public function assertEqualsModel(Model $expected, Model $actual, $message = null)
	{
		if (empty($message))
		{
			$message = "Failed asserting that " . get_class($actual) . " is " . get_class($expected) . ".";
		}
		$this->assertTrue((get_class($expected) === get_class($actual)) && ($expected->getId() == $actual->getId()), $message);
	}
}