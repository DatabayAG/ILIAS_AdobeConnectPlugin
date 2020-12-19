<?php
/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Exceptions/classes/class.ilException.php';

/**
 *
 * Main exception for adobe connect plugin
 *
 * @author Michael Jansen <mjansen@databay.de>
 *
 */
class ilAdobeConnectException extends ilException
{
	/**
	 *
	 * A message isn't optional as in build in class Exception
	 *
	 * @param string $a_message Message
	 * @param integer $a_code Code
	 * @access public
	 *
	 */
	public function __construct($a_message, $a_code = 0)
	{
		parent::__construct($a_message, $a_code);
	}
}
