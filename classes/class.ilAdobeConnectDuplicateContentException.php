<?php
/* Copyright (c) 1998-2011 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/AdobeConnect/classes/class.ilAdobeConnectException.php';

/**
 *
 * Exception should be thrown in case an uploaded content was handled as duplicate
 *
 * @author Michael Jansen <mjansen@databay.de>
 *
 */
class ilAdobeConnectDuplicateContentException extends ilAdobeConnectException
{
}
