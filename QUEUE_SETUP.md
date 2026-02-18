# Queue Setup (Production)

The "Send Report" feature queues emails in the background to avoid 504 Gateway Timeout on low-RAM servers (e.g. Digital Ocean 1GB).

## Prerequisites

- `QUEUE_CONNECTION=database` in `.env` (already configured)
- `jobs` table migrated (`php artisan migrate`)

## Cron ( required on live server )

Add this cron entry to process queued emails every minute:

```bash
* * * * * cd /path/to/your/app && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

Replace `/path/to/your/app` with your actual app path (e.g. `/var/www/bossingloanmonitoring`).

### How to add (Ubuntu/Digital Ocean)

1. SSH into your server
2. Run: `crontab -e`
3. Add the line above
4. Save and exit

## How it works

1. User clicks "Send Report" → emails are queued instantly (request finishes in ~1 second)
2. Cron runs every minute → processes pending jobs → sends emails
3. Emails are typically delivered within 1–2 minutes

## Failed jobs

If an email fails (e.g. SMTP auth error), it goes to the `failed_jobs` table. To retry:

```bash
php artisan queue:retry all
```

To see failed jobs:

```bash
php artisan queue:failed
```
