# Production email setup

AcademyHub uses email for owner verification, password recovery and staff invitations. Local development intentionally keeps `MAIL_MAILER=log`; production must use authenticated SMTP from a verified sender domain.

## Required environment values

Set these only in the production environment or secret manager. Do not commit real credentials.

```dotenv
APP_URL=https://academy.example.com
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.provider.example
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=no-reply@academy.example.com
MAIL_FROM_NAME="AcademyHub"
QUEUE_CONNECTION=database
```

Use port `465` with `MAIL_SCHEME=smtps` only when required by the provider. The public `APP_URL` must use HTTPS because verification and reset links are generated from it.

## Domain and delivery checklist

1. Verify the sender domain with the email provider.
2. Publish the provider's SPF and DKIM DNS records.
3. Add a DMARC policy and reporting address.
4. Use a dedicated transactional subdomain where possible.
5. Run the queue worker under a persistent process supervisor: `php artisan queue:work --tries=3 --backoff=30`.
6. After changing environment values, run `php artisan optimize:clear` and then `php artisan config:cache`.
7. Test registration verification, password reset and staff invitation from a staging domain before production launch.

Never expose SMTP credentials in the super-admin UI, API responses, logs or support exports.
