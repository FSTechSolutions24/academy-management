# WhatsApp Cloud API setup

The communications outbox uses Meta's WhatsApp Cloud API and sends approved utility templates only. Configure these server-side variables before enabling delivery:

```dotenv
WHATSAPP_PHONE_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_API_VERSION=
QUEUE_CONNECTION=database
```

Use the phone-number ID and a long-lived, least-privilege access token from the academy's Meta app. Choose a currently supported Graph API version and keep it configurable during upgrades. Do not expose these values to the browser or commit them to source control. Clear cached Laravel configuration after changing production variables.

Create and approve each template in WhatsApp Manager first. Register its exact provider template name, language and ordered body-variable names in the branch communication settings. The initial integration supports approved utility templates with text body parameters. Marketing/authentication templates and media components are intentionally not accepted by this endpoint.

Parents must have a recorded written, verbal or online opt-in before a message can enter the queue. Capture opt-outs as `withdrawn`; the worker checks current consent again immediately before contacting Meta. Failed messages can be retried from the outbox while consent remains active. The `sent` state currently means Meta accepted the API request; delivery/read state callbacks are a follow-up task and are not yet represented.

Run a persistent queue worker under the process supervisor, for example `php artisan queue:work --queue=default --tries=3`. Monitor failed jobs and the branch outbox. Configure provider webhooks and verify their signatures before treating a message as delivered or read.
