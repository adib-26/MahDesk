# MahbubDesk

A multi-workspace customer support desk. Agents work tickets, customers use a portal, and each workspace keeps its own queue, knowledge base, SLA targets, and automation rules.

The interface is a Laravel 12 and Inertia React app. Data stays in SQLite for local development. Outgoing mail is captured by [Mailpit](https://mailpit.axllent.org/). Inbound support mail can be pulled from the free [testmail.app](https://testmail.app) inbox.

## What you can do

- Run more than one workspace, with roles for owner, organization admin, manager, agent, and customer.
- Triage tickets by status, priority, category, assignee, and tag. Replies can include files. Internal notes stay off the customer portal.
- Open the analytics dashboard and follow a metric into the matching ticket list.
- Publish a help center, invite teammates, and manage SLA policies.
- Define automation rules: when a ticket is created, updated, or still unresolved, assign a team, notify someone, set an SLA, or escalate to a manager.
- Issue a Sanctum API token and call `/api/v1` for the signed-in user's organizations and tickets.

## Requirements

- PHP 8.2+ with the SQLite and Argon2 extensions
- Composer
- Node.js 20+
- Mailpit, if you want to read verification and notification mail in a browser

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
```

`php artisan migrate --seed` creates the demo workspaces and accounts below. Every seeded account uses the password `password`.

| Email | Who | Where they land |
| --- | --- | --- |
| `owner@desk.test` | Ava Chen, Northwind owner | Northwind Support desk |
| `admin@desk.test` | Marcus Hale, organization admin | Northwind Support desk |
| `agent@desk.test` | Priya Shah, agent | Assigned Northwind tickets |
| `super@desk.test` | Platform admin | Platform workspace console |
| `marina@harbor.test` | Marina Ortiz, Harbor owner | Harbor Digital desk |

Contacts such as `sofia@northline.com` are customers on tickets. They do not have a password until someone registers with that same email address. Registration links the contact and gives the new user the customer role. New accounts must verify their email before they can use the desk.

## Run it

```bash
composer run dev
```

That starts the PHP server, the queue worker, the log tail, Vite, and the scheduler. Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

The scheduler imports inbound mail and runs time-based automations about once a minute. You can also run those jobs by hand:

```bash
php artisan desk:poll-testmail
php artisan desk:run-automations
```

## Mail

**Mail the app sends** (verification, password reset, invitations, ticket notifications) goes to Mailpit when `MAIL_MAILER=mailpit`.

```bash
brew install mailpit
mailpit
```

Open [http://localhost:8025](http://localhost:8025). A verification link only works while you are still signed in, in the same browser.

**Mail customers send to the desk** is optional. Set `INBOUND_EMAIL_DRIVER=testmail` and fill `TESTMAIL_NAMESPACE` and `TESTMAIL_API_KEY` from the [testmail.app console](https://testmail.app/console). Do not commit those values. Each workspace receives mail at:

```text
{TESTMAIL_NAMESPACE}.{workspace-slug}@inbox.testmail.app
```

For the seeded Northwind workspace and a namespace of `tfv4l`, that address is `tfv4l.northwind-support@inbox.testmail.app`. Mail sent to `northwind-support@inbox.testmail.app` never arrives, because the namespace prefix is missing. The Email page in the desk shows the address the app is actually polling.

Postmark is supported as an alternate inbound driver. Leave `INBOUND_EMAIL_SECRET` empty and the webhook rejects requests.

## Tests

```bash
php artisan test
```

## License

The Laravel skeleton this app started from is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
