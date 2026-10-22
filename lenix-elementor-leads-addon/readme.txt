=== Lenix Leads Collector ===
Contributors: pshitik, yonifre
Tags: leads, crm, contact form db, elementor forms, form entries
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 2.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Save every Elementor, Contact Form 7 and WPForms submission in WordPress, manage leads with statuses and notes, and export to CSV.

== Description ==

**Lenix Leads Collector** keeps every form submission on your site in one place, so a lead is never lost to a failed email. Install it and it starts collecting - no configuration needed.

It works with **Elementor Pro forms**, **Hello Plus forms**, **Contact Form 7** and **WPForms** (Lite and Pro), including Elementor global forms.

= Collect =
* Automatically stores every submission as a lead (a private post type - never public on your front end)
* Remembers where the lead came from: UTM parameters, referrer, first landing page, IP address and browser
* Optional cross-page source tracking using a small first-party cookie (can be switched off)

= Manage =
* One list for all leads, plus a "Leads by form" overview with lead counts
* Lead statuses with colors (New, In Progress, Completed, Spam - or create your own) and quick status change from the list
* Bulk actions: change the status of many leads at once
* Filter by status and date range, and search inside the form values (names, emails, phones, messages - Hebrew and other languages included)
* Internal notes, phone call and SMS log, and email replies sent straight from the lead screen, with full response history
* Custom fields for your own data on each lead (for example "budget" or "assigned to")
* Decide who can view leads and who can edit them (Subscribers up to Administrators)

= Export =
* Export a form's leads to CSV, optionally by date range and status
* Export only the leads you selected
* Spreadsheet-safe: values that could be run as formulas are neutralized
* Hebrew and other UTF-8 text opens correctly in Excel

= Privacy and security =
* Form data is sanitized on save and escaped on output
* Every action is protected by nonces and capability checks
* Each lead is a normal WordPress post, so you can delete a lead whenever you need to (right to erasure)
* Optional automatic deletion of leads older than a number of days
* Lead data stays in your own database - nothing is sent to external servers

Available in English, Hebrew and French.

= Also by us: Maspik - Spam Protection =
Getting lots of junk submissions? Our sister plugin [Maspik](https://wordpress.org/plugins/contact-forms-anti-spam/) blocks spam in Elementor, CF7, WPForms and many other form plugins without CAPTCHA, so only real leads reach Leads Collector.

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen in WordPress.
2. Activate the plugin through the "Plugins" screen.
3. That's it - new submissions from your forms are saved automatically. Find them under **Leads Collector** in the admin menu.
4. Optional: open **Leads Collector > Settings** to choose who can view and edit leads, turn source tracking on or off, define custom fields and set automatic cleanup.

Leads are collected from the moment the plugin is activated; it does not import older submissions.

== Frequently Asked Questions ==

= Where can I find the leads? =
In the admin menu under **Leads Collector**. "All leads" shows everything, "By form" shows each form with its lead count and export button.

= Do I need Elementor Pro? =
For Elementor forms, yes - the Form widget is part of Elementor Pro (or Hello Plus). Contact Form 7 and WPForms are supported without Elementor.

= Does it import old submissions? =
No. Leads are saved from the moment the plugin is active.

= How do I export leads to CSV? =
Go to **Leads Collector > By form**, optionally click "Define Dates" to choose a date range and status, then click "Export leads to csv". To export specific leads, select them in "All leads" and choose "Export selected to CSV" from the bulk actions.

= Does it work with Elementor global forms? =
Yes, standard and global forms are both supported.

= Does it work with Hello Plus forms? =
Yes.

= Does it work with Contact Form 7 and WPForms? =
Yes. All Contact Form 7 submissions and WPForms submissions (Lite and Pro) are saved.

= Can I change a lead's status or reply to a lead? =
Yes. Change the status from the list or the lead screen, and use "Response to Lead" to add a note, log a call or SMS, or send an email reply. Everything is kept in the response history.

= Who can see the leads? =
By default contributors and above can view leads and editors and above can edit them. You can change this in **Leads Collector > Settings**. Leads contain personal data, so we recommend limiting access to the people who need it.

= Is the plugin GDPR friendly? =
Leads are normal WordPress posts stored in your own database, so you can delete any lead on request. You can also have old leads deleted automatically after a number of days. Remember to mention the data you collect in your privacy policy, and to get consent where the law requires it.

= Does the source tracking use cookies? =
If tracking is enabled, a small first-party cookie stores the UTM parameters and first landing page for 30 days. You can turn it off in **Settings > General Settings**. If your site needs a cookie consent banner, add this cookie to it or disable tracking.

= Is my data secure? =
Submitted data is sanitized when saved and escaped when displayed, and all actions require the right permissions. Please keep WordPress and the plugin up to date.

= Can I choose which form fields are stored? =
All fields of the submitted form are stored. Add or remove fields in your form builder.

= The numbers or text in my CSV look wrong in Excel =
Open the file with Excel's "Data > From Text/CSV" and choose UTF-8. Values that begin with =, +, - or @ are prefixed with an apostrophe on purpose, to stop spreadsheets from running them as formulas.

== Upgrade Notice ==

= 2.1.0 =
Security release - fixes a stored XSS vulnerability and adds bulk actions, filters and better search. Updating is strongly recommended.

== Changelog ==

= 2.1.0 =
* Security: Fixed unauthenticated stored Cross-Site Scripting (XSS) via form field names/labels (reported by Wordfence). All lead data is now sanitized when saved and escaped on every output.
* Security: Lead fields, form names, links and email recipients are escaped in all admin screens, the response history and the "Leads by form" table.
* Security: Lead source data (referrer, URL, UTM and first-visit cookies) is sanitized before it is stored.
* Security: CSV export now requires the edit-leads capability and a valid nonce, and neutralises spreadsheet formulas (CSV injection).
* Security: Status change, response history and custom-fields AJAX actions now enforce nonces, the configured lead permissions and per-lead capability checks. Statuses can no longer be created from user input.
* Security: Saving custom fields / lead status from the lead screen is protected with a nonce.
* Security: Permission settings accept only known capabilities.
* New: Bulk actions on the leads list - change status of many leads at once, or export only the selected leads to CSV.
* New: Filter the leads list by status and by date range.
* New: The leads search now also searches inside the form values (names, emails, phones, messages), including Hebrew and other non-English text.
* New: Choose a status when exporting a form's leads to CSV.
* New: Optional automatic deletion of old leads (Settings > General, off by default) for privacy / GDPR.
* Fix: CSV export no longer prints PHP 8.4 deprecation notices into the file.
* Fix: Custom fields screen (save, edit, delete, reorder) works again.
* Fix: UTM tracking cookie is now saved on the first visit, and uses SameSite=Lax.
* Fix: Response text is no longer saved with escaped slashes; the email address field is validated.
* Tweak: Fewer database queries on every page load, clearer plugin-row link, shared response handler code.


= 2.0.0 =
* Lenix becomes Leads Collector CRM, and now it's a lead management plugin, with a new UI and new features.
* New features:
    * Leads management
    * Leads response
    * Leads response history
    * Custom fields
    * Leads tags
    * Leads status
    * Leads source
    * Leads source data
    * Leads source data history

= 1.9.0 =
* Introduce universal form collector - Collects forms entries from Elementor, Cf7, WPForms and more with export to CSV.
* Add compatibility with WPForms forms
* Add compatibility with Cf7 forms


= 1.8.4 =
* Add compatibility with Hello Plus forms

= 1.8.3 =
* Security fix - Fix security vulnerability, thanks to Wordfence

= 1.8.2 =
* compatibility with WP 5.9.4

= 1.8.1 =
* Bugs fix

= 1.8 =
* Tweak - Improved UI UX
* Bugs fix

= 1.7 =
* Tweak - Improved saving global forms
* Tweak - Better performance
* Bugs fix

= 1.6.3 =
* Fix - Add compatibility to Editor
* Bugs fix

= 1.6.2 =
* Fix - Showing empty fields

= 1.6.1 =
* Fix - Supports custom form ID

= 1.6 =
* Tweak - Improved saving global forms

= 1.5 =
* Bug fix - php error

= 1.4 =
* Add French translation - Thanks to @momo-fr
* Insert the Leads page to the main menu
* Bug fix

= 1.3 =
* Bug fix

= 1.2 =
* Tweak! - Option to export on specific dates
* Bug fix

= 1.1 =
*  Improved UX

= 1.0 =
* Fix: Added compatibility with Elementor V1.13.0
* Fix Duplicate form bug
* Tweak! - Improved UI UX


= 0.4 =
* Fix encoding problems for field names
* Tweak! - Add date and time to export leads

= 0.3 =
* Tweak! – Added Export to CSV option

= 0.2 =
* Add Hebrew translation
* Improved UI UX

= 0.1 =
* Initial Public Beta Release