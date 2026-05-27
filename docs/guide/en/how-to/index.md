# How-to recipes

Task-oriented cookbook. Each recipe starts with "When to use this", shows working code, and ends with "Pitfalls". Skim by intent group.

## Routing and filters

- [Add a custom filter](custom-filter.md)
- [Pass kwargs from a filter to the handler](dependency-injection.md)

## Conversational flow

- [Build a wizard with scenes](scenes-wizard.md)
- [Handle deep-link `/start` payloads](deep-linking.md)
- [Track chat-member transitions](chat-member-updated.md)

## Storage and state

- [Use Redis or MongoDB for FSM storage](redis-mongo-fsm.md)
- [Plug in a custom storage backend](custom-storage.md)

## Media and content

- [Upload and download files](file-upload-download.md)
- [Send a media group (album)](media-group.md)
- [Show "typing…" while a slow handler runs](chat-action-typing.md)

## Payments and Web Apps

- [Sell something via Telegram Stars](telegram-stars-payment.md)
- [Validate Web App initData](web-app-data.md)

## Deployment and operations

- [Serve webhooks without amphp/http-server](webhook-without-amphp-server.md)
- [Run multiple bots from one process](multi-bot.md)
- [Rate-limit outgoing API calls](rate-limiting.md)
- [Acknowledge callback queries cleanly](callback-answer.md)
- [Run code outside the dispatcher](background-tasks.md)

## Quality

- [Handle errors globally](error-handling.md)
- [Test bots with the MockedSession](testing-bots.md)
- [Internationalise message payloads](i18n-payloads.md)
