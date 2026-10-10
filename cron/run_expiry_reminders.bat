@echo off
REM Trackify automated expiry reminder job.
REM Update these two paths if your XAMPP/PHP or project folder is different.
set PHP_EXE=C:\xampp\php\php.exe
set PROJECT_DIR=C:\xampp\htdocs\ITP104-System

"%PHP_EXE%" "%PROJECT_DIR%\cron\expiry_reminders.php" >> "%PROJECT_DIR%\cron\expiry_reminders.log" 2>&1
