# MAWIBLAH - Mailch!mp viz blek džek end hūkers
  
## Why?
- Good news - we have reached 2k newsletter subscribers.
- Bad news - we reached 2k newsletter subscribers.

Free tear of mailchimp is until 2k subscribers, but next tier is pretty expensive. 
I thought maybe 5$ per month or something, but no... we should spend about 50$ per month. Per month Karl.
Projects budget yearly is about 100 $ at the moment. 
"Fine... will do my own Mailchimp... with blackjack and hookers"

## What it does
- Sends out emails to the email list.
- Email list is collected via Gravity Forms entries. But one could add the mailing list manually.
- Email template that is sent out is generated via shortcodes. 
- Unsibscribe functuallity
- Importing list of unsubscribed from mailchimp
- Imports audience from Gravity Form entries

## Change log
--- 1.0.1 ---
- added some minimal action logger, for debuging to see why flow of sending out campaing.

--- initial MVP ---

Minimal functionality only to achieve my needs. Maybe will make it more universal at later point in time.

## In progress
- Logs for actions and errors


## Todo list
- Sand out by time
- GF sync seperte from audiences
- Hide default menus
- Import via files
- Add audience/subscribers
- Ajax email send out / cron job
- Count email send out failures
- Default template
- Overwrite the template via hooks or templates stored in theme
- in test mode  get emails before hand loop
- more detailed wp_mail error messages, maybe have to switch to smtp mailer
- move edit/create to the wordpress default functions, add  additional fields via hooks
