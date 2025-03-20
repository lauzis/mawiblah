# MAWIBLAH - Mailch!mp viz blek džek end hūkers
  
## What is it?
- It is a WordPress plugin that sends out emails to the list of subscribers.

## Why?
- Good news - we have reached 2k newsletter subscribers.
- Bad news - we reached 2k newsletter subscribers.

Free tear of mailchimp is until 2k subscribers, but next tier is pretty expensive.
I thought maybe 5$ per month or something, but no... we should spend about 50$ per month. Per month Karl.
Kind of steep increase as our projects budget  is about 100$ yearly at the moment.

So... "Fine... will do my own Mailchimp... with blackjack and hookers"

## What it does
- Sends out emails to the email list.
- Email list is collected via Gravity Forms entries. But one could add the mailing list manually.
- Email template that is sent out is generated via shortcodes.
- Unsubscribe functionality
- Importing list of unsubscribed from mailchimp
- Imports audience from Gravity Form entries

## Support
This is a free plugin, so support is limited.

Main idea is to create functionality that is needed for the particular project, there is no intention to make it work
on all possible configurations and setups.

## Change log
### --- 1.0.5 ---
-- fix for two messages at the same time at the unsub

### --- 1.0.4 ---
- fixed issue with the registering visit from link stats

### --- 1.0.3 ---
- removed somed debug code 
- fixed issue with wpml translations, probably this was due to the order of plugin registration or something. And at time
when email tempalte was read the wpml was not inicialized. Rewrote that request for template would go through rest request. 

### --- 1.0.2 ---
- added to log function that it adds extra data to the content of the log
- fixed issue that in some cases was sending twice to same email, issue was that in the source there was same address 
used  with some of the leters capitalized

### --- 1.0.1 ---
- added some minimal action logger, for debugging to see why flow of sending out campaign.

### --- initial MVP ---

Minimal functionality only to achieve my needs. Maybe will make it more universal at later point in time.


## Todo
- Sand out by time
- GF sync seperte from audiences, so audiences would come only from "Mawiblac audiences", 
and there would be some syncing mechanism. checking last entry. if last entry is newer than last sync, then sync.
- Hide default menus
- Import via files
- Add audience/subscribers
- Ajax email send out / cron job
- Count email send out failures
- Default template
- Overwrite the template via hooks or templates stored in theme
- in test mode  get emails beforehand loop
- more detailed wp_mail error messages, maybe have to switch to smtp mailer
- move edit/create to the WordPress default functions, add  additional fields via hooks
- Update selftests
  - Check if there is email templates
  - Check if theme has email templates
