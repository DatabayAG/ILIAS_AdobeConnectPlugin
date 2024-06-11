# ILIAS_AdobeConnectPlugin
ILIAS Plugin for Adobe Connect Virtual Classrooms
* For ILIAS versions: 7.0 - 7.999
* Tested with Adobe Connect API-Version 12.5

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
"SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL"
in this document are to be interpreted as described in
[RFC 2119](https://www.ietf.org/rfc/rfc2119.txt).

**Table of Contents**

* [Requirements](#requirements)
* [Install](#install)
* [General](#general)
* [Own Server Mode](#own-server-mode)
* [DFN MODE](#dfn-mode)

## Requirements

* PHP: [![Minimum PHP Version](https://img.shields.io/badge/Minimum_PHP-8.1.x-blue.svg)](https://php.net/) [![Maximum PHP Version](https://img.shields.io/badge/Maximum_PHP-8.1.x-blue.svg)](https://php.net/)
* ILIAS: [![Minimum ILIAS Version](https://img.shields.io/badge/Minimum_ILIAS-9.x-orange.svg)](https://ilias.de/) [![Maximum ILIAS Version](https://img.shields.io/badge/Maximum_ILIAS-9.x-orange.svg)](https://ilias.de/)

## Install

This plugin MUST be installed as a
[Repository Plugin](https://docu.ilias.de/goto_docu_pg_29962_42.html).

The files MUST be saved in the following directory:

	<ILIAS>/Customizing/global/plugins/Services/Repository/RepositoryObject/AdobeConnect

Correct file and folder permissions MUST be
ensured by the responsible system administrator.

The plugin's files and folder SHOULD NOT be created,
as root.

1. Clone this repository 
   `$ git clone https://github.com/DatabayAG/ILIAS_AdobeConnectPlugin`
2. Move the project to the ILIAS-plugin-directory
   `$ mv ILIAS_AdobeConnectPlugin <ILIAS_DIRECTORY>/Customizing/global/plugins/Services/Repository/RepositoryObject/AdobeConnect`
3. Login to ILIAS with an administrator account (e.g. root)
4. Select **Plugins** from the **Administration** main menu drop down.
5. Search the **AdobeConnect** plugin in the list of plugin and choose **Activate** from the **Actions** drop down.
6. Choose **Configure** from the **Actions** drop down and enter the required data.

## General
* Please keep in mind that the *Adobe Connect-Registration-Mode* cannot be changed (only by direct database access) after it is selected once.

![Login Policy](https://mjansendatabay.github.io/ILIAS/Plugins/AdobeConnect/loginpolicy.png)

* An API connection by a proxy is currently not implemented.
* SWITCH AAI-Mode is not maintained anymore

### Own Server Mode
* Authentication: Please ensure that the setting "Email-Adresse für Anmeldung verwenden" is disabled (Administration » Benutzer und Gruppen » Anmelde- und Kennwortrichtlinien).

### DFN Mode
* The creation of user folders is not possible (because of API limitations). The ILIAS plugins automatically disables the checkbox to activate the usage of folders if you choose the DFN mode. 
* The authentication and mapping is done by the user's email address ( https://git.vc.dfn.de/lmsapi/doc/wikis/lms-user-create ). This can lead to authentication/user account mapping issues, if multiple ILIAS accounts use the same email address (which is possible in ILIAS).
* If a meeting is started once, changes concerning roles (moderator, etc.) have no effect on the running meeting (although the API acknowledges the permission change requests with a: OK, DONE)
