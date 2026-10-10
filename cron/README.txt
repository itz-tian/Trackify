TRACKIFY AUTOMATED EXPIRY REMINDERS
===================================

The expiry reminder script is:
    cron/expiry_reminders.php

It checks active documents with an expiry date and creates in-app notifications
for the document uploader, the department head of the document's department,
and active System Admins.

Reminder checkpoints:
    30 days before expiry
    14 days before expiry
    7 days before expiry
    3 days before expiry
    1 day before expiry
    On the expiry date
    After expiry (one notification checkpoint)

Duplicate notifications are prevented automatically.

WINDOWS / XAMPP SETUP
---------------------
1. Make sure PHP CLI works at:
   C:\xampp\php\php.exe

2. Open cron/run_expiry_reminders.bat and update PROJECT_DIR if your
   ITP104-System folder is not inside C:\xampp\htdocs.

3. Test it by double-clicking the BAT file or running it from Command Prompt.
   A successful run prints a completion message and creates in-app notifications.

4. Open Windows Task Scheduler.

5. Create a Basic Task named:
   Trackify - Daily Expiry Reminders

6. Trigger: Daily. Recommended time: 8:00 AM.

7. Action: Start a program.
   Program/script:
       C:\xampp\htdocs\ITP104-System\cron\run_expiry_reminders.bat

8. Finish the task. Windows will run the reminder job every day.

IMPORTANT
---------
The reminder job does not change the database structure, so you do NOT need
to re-import schema.sql for this feature. It only inserts records into the
existing notifications table.
