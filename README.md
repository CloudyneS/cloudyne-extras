# Cloudyne Extras
Extra functionality for Wordpress on Cloudyne Hosting

## Functionality
### Environmental SMTP Configuration
This plugin allows you to configure SMTP settings based on the environment. This is useful for when you have customers that should be restricted to sending emails from a certain email address.
```bash
# SMTP Host to send email
SMTP_HOST='smtp.gmail.com'

# The port to use
SMTP_PORT=25

# Authentication settings
SMTP_AUTH=True
SMTP_USER='someuser@test.com'
SMTP_PASS='abcdefgh'

# Security Settings
SMTP_SECURE=False
SMTP_AUTOTLS=False
SMTP_STARTTLS=False

# Provide a default sender name and email
SMTP_FROM='default@mail.com'
SMTP_FROM_NAME='From Name'

# Restrict the user changing email settings to only allow certain domains and/or emails to use as sender
SMTP_ALLOWONLY_DOMAINS='domain.com,domain2.com,domain3.com'
SMTP_ALLOWONLY_EMAILS='mail1@user.com,mail2@user.com'

# Force the site to only use the specified email and sender name
SMTP_FORCE_FROM='forced@mail.com'
SMTP_FORCE_FROM_NAME='ForcedFromName'
```


=== Cloudyne Extras ===
Contributors: hlashbrooke
Donate link: http://www.hughlashbrooke.com/donate
Tags: wordpress, plugin, template
Requires at least: 3.9
Tested up to: 4.0
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This is where you craft a short, punchy description of your plugin

== Description ==

This is where you can give a much longer description of your plugin that you can use to explain just how it awesome it really is.

== Installation ==

Installing "Cloudyne Extras" can be done either by searching for "Cloudyne Extras" via the "Plugins > Add New" screen in your WordPress dashboard, or by using the following steps:

1. Download the plugin via WordPress.org
1. Upload the ZIP file through the 'Plugins > Add New > Upload' screen in your WordPress dashboard
1. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. Description of first screenshot named screenshot-1
2. Description of second screenshot named screenshot-2
3. Description of third screenshot named screenshot-3

== Frequently Asked Questions ==

= What is the plugin template for? =

This plugin template is designed to help you get started with any new WordPress plugin.

== Changelog ==

= 1.0 =
* 2012-12-13
* Initial release

== Upgrade Notice ==

= 1.0 =
* 2012-12-13
* Initial release