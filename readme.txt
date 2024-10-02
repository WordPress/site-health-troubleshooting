=== Troubleshooting ===
Tags: health check, site health
Contributors: Clorith
Requires at least: 5.8
Requires PHP: 7.1
Tested up to: 6.6
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Provides a Troubleshooting Mode to help with support and debugging.

== Description ==

Troubleshooting Mode was initially a feature introduced via the Health Check plugin, and is now available as a standalone plugin.

The most common, and reliable, means of troubleshooting a website using WordPress, is by first disabling all plugins, and using one of the default themes that come with the software.
The best way to do this, is by the use of a staging website, but not all website hosting providers have this feature, or it may not be part of the plan you are currently on.

Troubleshooting Mode will do its best to allow a site maintainer to disable plugins, and switch themes, without affecting regular visitors to your website.

== Frequently Asked Questions ==

= How can I disable Troubleshooting Mode? =

Troubleshooting Mode has various ways to be disabled, depending on your situation. Normally you would either click the "Disable Troubleshooting Mode" button within your admin dashboard, or from the "Troubleshooting Mode" menu item in the admin bar.

If you are unable to access your admin dashboard, or your admin bar for any reason, either signing out from your website, clearing all cookies for your website, or closing all your browser windows will also remove the cookies used to declare your current account as being in Troubleshooting Mode.

= My website looks broken after using Troubleshooting Mode! =

Unfortunately, despite our best efforts, some caching solutions are either so complex, or so aggressive, that they do not respect Troubleshooting Mode's directives to not cache any content.

It is recommended to clear your caches after completing your troubleshooting, this can usually be done from your caching plugin's settings, or by disabling and re-enabling the caching plugin. Note that your hosting provider may also have a dedicated button to clear its caching from their own control panel, if this is offered to you.

= Can I contribute to this plugin? =

Yes, the plugin is open source and available on the [WordPress/site-health-troubleshooting GitHub repository](https://github.com/wordpress/site-health-troubleshooting), and we welcome all types of contributions!

== Changelog ==

= 1.0.1 (2024-10-02) =
* Fixed: Remove some strict typecasting on filters where plugins may return values WordPress core does not expect, to avoid causing fatal errors.
* Fixed: Removed some more strict typecasting causing issues when switching between a block and non-block theme in troubleshooting mode.
* Improvement: Made the plugin attempt to use symlinks instead of copying files when setting up the mu-plugin.

= 1.0.0 (2024-09-21)
* Initial release
