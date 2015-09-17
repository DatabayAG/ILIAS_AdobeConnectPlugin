# ILIAS_AdobeConnectPlugin
ILIAS Plugin for Adobe Connect Virtual Classrooms
* For ILIAS-Versions: 4.3.0 - 4.4.9

## Installation Instructions
1. Clone this repository to <ILIAS_DIRECTORY>/Customizing/global/plugins/Services/Repository/RepositoryObject/AdobeConnect
2. Login to ILIAS with an administrator account (e.g. root)
3. Select **Plugins** from the **Administration** main menu drop down.
4. Search the **AdobeConnect** plugin in the list of plugin and choose **Activate** from the **Actions** drop down.
5. Choose **Configure** from the **Actions** drop down and enter the required data.

## Known Issues

### General
* Please keep in mind that the *Adobe Connect-Registration-Mode* cannot be changed (only by direct database access) after it is selected once.
* An API connection by a proxy is currently not implemented.

### Own Server Mode
* Authentication: Please ensure that the setting "Email-Adresse für Anmeldung verwenden" is disabled (Administration » Benutzer und Gruppen » Anmelde- und Kennwortrichtlinien).

### DFN Mode
* The creation of user folders is not possible (because of API limitations). The ILIAS plugins automatically disables the checkbox to activate the usage of folders if you choose the DFN mode. 
* The authentication and mapping is done by the user's email address ( https://git.vc.dfn.de/lmsapi/doc/wikis/lms-user-create ). This can lead to authentication/user account mapping issues, if multiple ILIAS accounts use the same email address (which is possible in ILIAS).
* If a meeting is started once, changes concerning roles (moderator, etc.) have no effect on the running meeting (although the API acknowledges the permission change requests with a: OK, DONE)
