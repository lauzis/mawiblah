# MAWIBLAH - Mailch!mp viz blek džek end hūkers

## What is it?
- It is a WordPress plugin that sends out emails to the list of subscribers.

## Who's it for?
Generally, it's for me; it's a "weekend project" that is both useful and interesting to me.  
It could be useful for small projects with tight budgets or no income streams.  
It is not suited for sending out 100k emails. It's possible, but it will take a long time as, for now, the plugin sends "individual" emails, which is necessary in my case.

## Why?
- Good news - we have reached 2k newsletter subscribers.
- Bad news - we reached 2k newsletter subscribers.

The free tier of Mailchimp is until 2k subscribers, but the next tier is pretty expensive.
I thought maybe $5 per month or something, but no... we should spend about $50 per month. Per month, Karl.
Kind of a steep increase as our project's budget is about $100 yearly at the moment.

So... "Fine... will do my own Mailchimp... with blackjack and hookers"

![Mawiblah name](readme-assets/mawiblah.jpg)

## What it does
- Sends out emails to the email list.
- The email list is collected via Gravity Forms entries, but one can add the mailing list manually.
- The email template that is sent out is generated via shortcodes.
- Includes unsubscribe functionality.
- Imports a list of unsubscribed users from Mailchimp.
- Imports the audience from Gravity Form entries.
- Tracks clicks for the campaigns.
- Tracks the timing of clicks for the campaigns.
- Logs the actions. 

## Support
This is a free plugin, so support is limited.

The main idea is to create functionality that is needed for the particular project. There is no intention to make it work
on all possible configurations and setups.

## Change log

### --- 1.0.13 ---
- Test and approval implemented
- Moved email sending to an ajax async process

### --- 1.0.12 ---
- Added an action page with the ability to clear logs and manually sync entries/emails with Gravity Forms.
![Action page](readme-assets/action-page.png)

### --- 1.0.11 ---
- Added a meta-field to the subscribers' post-type for the last interaction and updated it after an email is sent.
- Added a meta-settings field to control the time between emails to the same subscriber.
  
![dont disturb settings](readme-assets/dont-disturb-threshold.png)

### --- 1.0.10 ---
- Implemented a setting to skip actual email sending for testing/debugging purposes.
- Displayed settings output on the test page.

![Settings output in the test page](readme-assets/settings-output-in-test.jpg)

### --- 1.0.9 ---
- Introduced a dedicated settings page in the admin interface to provide a centralized location for configuration.
- Added options to control email intervals and enable debugging with IP restrictions.
- Added the ability to toggle database logging via the settings page.
- Testing/Shout-out to [coderabit.ai](https://coderabit.ai) for the help with the code. Will see how it goes, but for now it seems helpful.
  
![Settings page](readme-assets/settings.jpg)

### --- 1.0.8 ---
- Saved click time for statistics, allowing analysis of the most "active" times for opening emails.
- Fixed a logical issue where all subscribers were flagged as having already been sent an email to that address.

### --- 1.0.7 ---
- Updated some logging mechanisms and added logging for skipped emails.

### --- 1.0.6 ---
- Fixed nonce issues for AJAX requests.

### --- 1.0.5 ---
- Fixed an issue where two messages were sent simultaneously during the unsubscribed process.

### --- 1.0.4 ---
- Fixed an issue with registering visits from link statistics.

### --- 1.0.3 ---
- Removed some debug code.
- Fixed an issue with WPML translations, likely caused by the plugin registration order. Adjusted the email template request to go through a REST request to ensure WPML initialization. 

### --- 1.0.2 ---
- added to the log function that it adds extra data to the content of the log
- fixed issue that in some cases was sending twice to the same email, issue was that in the source there was the same address  
used with some letters capitalized

### --- 1.0.1 ---
- Added a minimal action logger for debugging purposes to trace the flow of sending out campaigns.

### --- initial MVP ---
- Implemented minimal functionality to meet specific needs. Potential for making it more universal in the future.
![Mvp version](readme-assets/mvp.jpg)

