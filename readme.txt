=== Plugin Name ===
Contributors: belinde
Donate link: http://e2net.it
Tags: security, filesystem, permissions, chmod, folders, files
Requires at least: 3.1.0
Tested up to: 3.9
Stable tag: 0.5.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Protect folders and files from unhautorized changes managing filesystem permissions.

== Description ==

Protect folders and files from unhautorized changes managing filesystem permissions. You can configure the permission mask for file and folders in "protected" and "writeable" status, and with a single click you can switch between them. When you enable writing a cron event is set and the protected status will be applied automatically after 10 minutes.

**Please check carefully the configuration before enabling protection!** If the default permission mask isn't correct for your server **Wordpress will stop working**, and you'll need to restore the correct permission manually.

Pay attention: the suggested configuration is, obviously, only a suggestion: depending on various system configuration the detection could be suboptimal or erroneous.

**New in 0.5:** automatic updates should work regularly; the protection will disabled and re-enabled, hopefully without pain. But this feature is still experimental and I can't debug it untill next minor release of WP.

== Installation ==

Just follow any standard plugin installation procedure, as you wish.

== Frequently Asked Questions ==

= Can I use this plugin on Windows/Mac/Solaris? =

On Windows surely NOT, on other systems maybe. AutoCHMOD intensively use the [PHP chmod command](http://it2.php.net/manual/en/function.chmod.php): if this funcion is usable on your system, AutoCHMOD would run just fine.

= Does this plugin works out-of-the-box? =

Maybe, or maybe not. You must double-check the configuration of the permission you'll grant to your file and folders: if the configuration isn't correct for your server, Wordpress could not run anymore and you'll need to restore the correct permissions manually. In future releases I'll try to check the configuration BEFORE applying it.

== Screenshots ==

1. The config page when protection is active.
2. The config page when protection isn't active. Note the countdown on the admin button.
3. The alert on plugin installation page when protection is active. The same alert is shown also on edit plugin page and installation and edit theme pages.
4. The Help tab. Less text in the page, more comfort for the user.

== Changelog ==

= 0.5 =
* Let the automatic updates happen!

**0.5.1** Fixed the behaviour of the admin bar button on multisite installation: the option are now saved on site_meta

**0.5.2** Fixed a bug who didn't permit saving permission options on some network installations

= 0.4 =
* Check suggested configuration with a real case
* Help screen
* Disable protection forever
* Animated countdown when protection is disabled.

**0.4.1:** Completed italian localization, minor bug fixes

**0.4.2:** Removed debug information. Sorry, my fault

= 0.3 =
* Multisite friendly (config page is in network Settings section)
* Navbar button bring to option page if configuration hasn't been verified
* Suggest permission comparing a file created in system's temporary folder and the Wordpress root

= 0.2 =
* First public release.
* Improved options page.
* Configuration of permissions mask.
* Localization (english, italian)

= 0.1 =
* First attempt. 
* Single file plugin, no fancy options.

== Upgrade Notice ==

= 0.2 =
First public release, because 0.1 was a very buggy alpha version.

= 0.3 =
Not a big improvement if you have already installed AutoCHMOD, but new users will enjoy it.

= 0.4 =
The configuration detection has been REALLY improved, and now you can trust it. There's a bit of eye-candy, also.
**0.4.1:** Minor bug fixes
**0.4.2:** Minor bug fixes

= 0.5 =
Experimental feature: disable protection at the start of an automatic WP upgrade and re-enable at upgrade finished (or after 60 minutes if anything goes wrong). I haven't been able to really test it, so cross your fingers and hope for the best! But the worst it could happen is that the protection remains disactivated, or that WP dosn't get upgraded at all. Please inform me if anything goes wrong!
**0.5.1:** On multisite installation options are now saved in sitemeta: you'll need to reconfigure the plugin. I'm truly sorry.