<?php

/**
 * This file is part of the Nextras community extensions of Nette Framework
 *
 * @license    MIT
 * @link       https://github.com/nextras
 * @author     Jan Skrasek
 */

namespace Nextras\Forms\Controls;

use Nextras\Forms\ComponentControl;



/**
 * Form control for selecting date range
 *
 * @author   Jan Skrasek
 */
class DateRangePicker extends ComponentControl
{

	/**
	 * Sets values, min & max keys
	 * @param  array
	 * @return static
	 */
	public function setValue($value)
	{
		if (isset($value['min'])) {
			$this['min']->setValue($value['min']);
		}
		if (isset($value['max'])) {
			$this['max']->setValue($value['max']);
		}

		return $this;
	}



	/**
	 * Returns value
	 * @return array
	 */
	public function getValue()
	{
		return array(
			'min' => $this['min']->getValue(),
			'max' => $this['max']->getValue(),
		);
	}



	protected function createComponentMin()
	{
		return $this->createPickerInstance();
	}



	protected function createComponentMax()
	{
		return $this->createPickerInstance();
	}



	protected function createPickerInstance()
	{
		return new DatePicker;
	}

}
