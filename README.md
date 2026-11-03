# frootbox/webhooks

Webhooks for the [Frootbox CMS](https://github.com/Frootbox/frootbox):
tell other services about changes, signed after
[Standard Webhooks](https://www.standardwebhooks.com/) and sent through
the queue.

- An admin sets up an address and the actions it hears about, by name
  (`page.publish`) or pattern (`page.*`, `frootbox/news/*`):
  `frootbox/webhooks/webhook.create`, `.update`, `.delete`, `.pause`,
  `.resume`, `.rotate-secret`, `.test`
- After every action that matches, a delivery goes into the queue. The
  body is `{"type", "timestamp", "data": {"action", "entry", "actor",
  "input", "result"}}`; sensitive input is `***`, of the result only a
  number, a boolean or the `id` of an object goes out
- Each delivery has the headers `webhook-id`, `webhook-timestamp` and
  `webhook-signature`, is tried 8 times over about 18 hours and kept for
  30 days (`frootbox/webhooks/delivery.list`, `delivery.retry`)
- A webhook without success for 5 days is paused
- Only `https://` and addresses on the internet, also after DNS; in
  development `http://` and local addresses work too
- The screen "Webhooks" in the backend

To check a webhook on the receiving side, use any library for Standard
Webhooks, or `Frootbox\Core\Security\StandardWebhooks::verify()` in
another Frootbox.

MIT licensed.
