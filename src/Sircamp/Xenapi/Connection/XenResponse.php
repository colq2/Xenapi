<?php namespace Sircamp\Xenapi\Connection;

use ReflectionClass;

class XenResponse
{

	private $value;
	private $status;
	private $errorDescription = array();

	public function __construct($args)
	{

		foreach ($args as $key => $argument)
		{
			$class    = new ReflectionClass('Sircamp\Xenapi\Connection\XenResponse');
			$property = $class->getProperty($key);
			$property->setAccessible(true);
			$property->setValue($this, $argument);

		}

	}

	/**
	 * Gets the value of Value.
	 *
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Sets the value of Value.
	 *
	 * @param mixed $value the value
	 *
	 * @return self
	 */
	public function _setValue($value)
	{
		$this->value = $value;

		return $this;
	}

	/**
	 * Gets the value of Status.
	 *
	 * @return mixed
	 */
	public function getStatus()
	{
		return $this->status;
	}

	/**
	 * Sets the value of Status.
	 *
	 * @param mixed $Status the status
	 *
	 * @return self
	 */
	private function _setStatus($Status)
	{
		$this->status = $Status;

		return $this;
	}

	/**
	 * Gets the value of ErrorDescription.
	 *
	 * @return mixed
	 */
	public function getErrorDescription()
	{
		return $this->errorDescription;
	}

	/**
	 * Sets the value of ErrorDescription.
	 *
	 * @param mixed $ErrorDescription the error description
	 *
	 * @return self
	 */
	private function _setErrorDescription($ErrorDescription)
	{
		$this->errorDescription = $ErrorDescription;

		return $this;
	}
}

?>