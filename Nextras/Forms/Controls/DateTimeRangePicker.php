<?php

/**
 * This file is part of the Nextras community extensions of Nette Framework
 *
 * @license    MIT
 * @link       https://github.com/nextras
 * @author     Jan Skrasek
 */

namespace Nextras\Forms\Controls;



/**
 * Form control for selecting date-time range
 *
 * @author   Jan Skrasek
 */
class DateTimeRangePicker extends DateRangePicker
{

	protected function createPickerInstance()
	{
		return new DateTimePicker;
	}

}
