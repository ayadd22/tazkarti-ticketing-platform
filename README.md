# Tazkarti - Event Ticket Reservation API

A RESTful API built with Laravel 12 for managing events, ticket types, reservations, and ticket issuance.

Tazkarti provides an API-first backend that supports different user roles, event discovery, ticket reservations, inventory management, payment confirmation, automatic reservation expiration, and asynchronous ticket processing.

---

## 1. Project Overview

Tazkarti is an event ticketing platform where users can browse events, reserve tickets, and receive their tickets after payment confirmation.

The system supports three main roles:

- **Admin:** Manages and oversees the platform.
- **Organizer:** Owns and manages their events.
- **Buyer:** Browses published events and makes reservations.

The application follows an API-first architecture. It returns JSON responses instead of relying on server-rendered pages.

### Main Features

- User authentication using Laravel Sanctum.
- Role-based authorization.
- Event listing with search and filtering.
- Event and ticket type management architecture.
- Ticket reservation with inventory protection.
- Reservation expiration after 10 minutes.
- Payment confirmation workflow.
- Automatic ticket generation.
- Ticket SMS notification integration point.
- Reservation confirmation emails.
- Redis caching and cache invalidation.
- Background jobs and queues.
- Laravel Horizon monitoring.
- Centralized API exception handling.
- Automated tests.
- Event cancellation workflow.
- Automatic reservation expiration and inventory release.
- Cancellation notifications through background jobs.
- Refund state handling for paid reservations.

---

## 2. Technology Stack

| Technology | Purpose |
|---|---|
| PHP 8.2+ | Backend programming language |
| Laravel 12 | Backend framework |
| MySQL / SQLite | Database management and testing |
| Laravel Sanctum | API authentication |
| Redis | Queue and caching infrastructure |
| Laravel Horizon | Queue monitoring |
| Predis | Redis client |
| PHPUnit | Automated testing |
| Faker | Generating test data |
| Laravel Eloquent | Database ORM |

### Why Laravel?

Laravel provides built-in solutions for authentication, database operations, validation, authorization, queues, events, notifications, and testing.

Using these features reduces the need to implement common backend infrastructure manually.

---

## 3. Project Architecture

The application separates responsibilities into different layers.

```text
app/
├── Console/
│   └── Commands/
├── Contracts/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Listeners/
├── Mail/
├── Models/
├── Observers/
├── Policies/
├── Providers/
└── Services/
```

### Why this structure?

Each directory has a specific responsibility:

- **Controllers:** Receive HTTP requests and return API responses.
- **Requests:** Validate incoming data and authorize requests.
- **Resources:** Control the JSON structure returned to clients.
- **Models:** Represent database entities and their relationships.
- **Policies:** Handle authorization rules.
- **Services:** Contain business logic.
- **Contracts:** Define interfaces for external services.
- **Events:** Represent important application actions.
- **Listeners:** React to dispatched events.
- **Jobs:** Execute background tasks.
- **Observers:** React to model lifecycle changes.
- **Enums:** Define fixed and controlled values.
- **Providers:** Register application bindings and observers.

This separation keeps controllers lightweight and prevents business logic from being duplicated across the application.

---

## 4. Database Design and Entities

The database is designed around the main entities of an event ticketing system.

### 4.1 User

Represents a registered platform user.

**Important fields:**

- `name`
- `email`
- `phone`
- `password`
- `role`

**Relationships:**

- A User can organize many Events.
- A User can make many Reservations as a buyer.
- A User can have many Media records.

**Why separate roles in one User model?**

All users share the same authentication information and basic properties. Using a role field avoids creating separate authentication tables for admins, organizers, and buyers.

The `UserRoleEnum` restricts the role to known values.

### 4.2 City

Represents a city where event venues are located.

**Important fields:**

- `name`
- `slug`

**Relationships:**

- A City has many Venues.

The slug provides a stable URL-friendly identifier for city-based filtering.

### 4.3 Venue

Represents the physical location where an event takes place.

**Important fields:**

- `city_id`
- `name`
- `address`
- `capacity`

**Relationships:**

- A Venue belongs to one City.
- A Venue has many Events.
- A Venue can have many Media records.

A separate Venue entity avoids repeating location information in every Event.

### 4.4 Event

Represents an event published or managed by an organizer.

**Important fields:**

- `organizer_id`
- `venue_id`
- `title`
- `description`
- `event_type`
- `status`
- `starts_at`
- `ends_at`
- `published_at`

**Relationships:**

- An Event belongs to one Organizer.
- An Event belongs to one Venue.
- An Event has many TicketTypes.
- An Event can have many Media records.

**Why are Event and TicketType separate?**

An event can offer several ticket categories with different prices and quantities.

For example:

- General Admission
- VIP
- Premium
- Early Bird

Separating them allows each category to maintain its own inventory and price.

### 4.5 TicketType

Represents a ticket category available for an event.

**Important fields:**

- `event_id`
- `name`
- `description`
- `price`
- `total_quantity`
- `reserved_quantity`
- `sold_quantity`
- `max_per_order`

**Relationships:**

- A TicketType belongs to one Event.
- A TicketType has many ReservationItems.
- A TicketType has many Tickets.

**Inventory calculation:**

```text
Available Quantity =
Total Quantity - Reserved Quantity - Sold Quantity
```

The three inventory values have different meanings:

- `total_quantity`: Total tickets available for this category.
- `reserved_quantity`: Tickets temporarily held by pending reservations.
- `sold_quantity`: Tickets belonging to paid reservations.

Keeping these values separate prevents temporarily reserved tickets from being sold again.

### 4.6 Reservation

Represents a buyer's reservation.

**Important fields:**

- `buyer_id`
- `status`
- `total_amount`
- `expires_at`
- `paid_at`
- `refunded_at`
- `confirmation_email_sent_at`

**Relationships:**

- A Reservation belongs to one Buyer.
- A Reservation has many ReservationItems.
- A Reservation has many Tickets.

A Reservation represents the complete booking operation, even when the buyer reserves multiple ticket categories.

### 4.7 ReservationItem

Represents one ticket category and its quantity inside a reservation.

**Important fields:**

- `reservation_id`
- `ticket_type_id`
- `quantity`
- `unit_price`

**Relationships:**

- A ReservationItem belongs to one Reservation.
- A ReservationItem belongs to one TicketType.

**Why use a separate ReservationItem table?**

A reservation can contain multiple ticket categories.

For example:

```text
Reservation #15

General Admission:
Quantity: 2
Unit Price: 150

VIP:
Quantity: 1
Unit Price: 350
```

The `unit_price` field stores a price snapshot at reservation time.

This is important because ticket prices may change later. Existing reservations must retain their original prices.

The unique constraint on `reservation_id` and `ticket_type_id` prevents duplicate rows for the same ticket category in one reservation.

### 4.8 Ticket

Represents an individual ticket issued to a buyer.

**Important fields:**

- `reservation_id`
- `ticket_type_id`
- `code`
- `status`
- `file_path`
- `sms_sent_at`

**Relationships:**

- A Ticket belongs to one Reservation.
- A Ticket belongs to one TicketType.

**Why have a Ticket entity instead of storing only the quantity?**

A quantity alone cannot represent individual tickets.

If a buyer reserves three tickets, the system creates three separate Ticket records, each with its own unique code and status.

This allows tickets to be generated, delivered, and tracked individually.

### 4.9 Media

Represents images and media files associated with different models.

**Important fields:**

- `mediable_id`
- `mediable_type`
- `collection`
- `path`
- `alt_text`

**Relationships:**

Media uses a polymorphic relationship.

It can belong to:

- Event
- Venue
- User

**Why use a polymorphic relationship?**

Instead of creating separate image tables for events, venues, and users, one Media table can serve all of them.

The `collection` field identifies the media category, such as:

- poster
- gallery
- banner
- logo
- avatar

---

## 5. Entity Relationships

The main database relationships can be summarized as follows:

```text
User
 ├── hasMany Events (as organizer)
 ├── hasMany Reservations (as buyer)
 └── morphMany Media

City
 └── hasMany Venues

Venue
 ├── belongsTo City
 ├── hasMany Events
 └── morphMany Media

Event
 ├── belongsTo User (organizer)
 ├── belongsTo Venue
 ├── hasMany TicketTypes
 └── morphMany Media

TicketType
 ├── belongsTo Event
 ├── hasMany ReservationItems
 └── hasMany Tickets

Reservation
 ├── belongsTo User (buyer)
 ├── hasMany ReservationItems
 └── hasMany Tickets

ReservationItem
 ├── belongsTo Reservation
 └── belongsTo TicketType

Ticket
 ├── belongsTo Reservation
 └── belongsTo TicketType
```

---

## 6. Enums

Enums are used to represent fixed values and prevent inconsistent strings throughout the application.

### UserRoleEnum

```text
admin
organizer
buyer
```

### EventStatusEnum

```text
draft
published
cancelled
postponed
```

### EventTypeEnum

```text
concert
conference
theater
```

### ReservationStatusEnum

```text
pending
paid
refunded
expired
```

### TicketStatusEnum

```text
pending
valid
used
cancelled
```

**Why use Enums instead of plain strings?**

Enums provide a controlled set of values, improve readability, reduce typing mistakes, and allow Eloquent models to cast database values directly into enum cases.

---

## 7. Authentication and Authorization

### 7.1 Laravel Sanctum

Laravel Sanctum is used for API token authentication.

After successful login, the client receives an access token and sends it in subsequent requests:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
```

The token is used to authenticate protected API endpoints.

### 7.2 Authentication Features

The AuthController handles:

- Register
- Login
- Logout
- Get authenticated user

New registrations are assigned the Buyer role by default.

The registration process does not allow clients to assign themselves Admin or Organizer roles.

The `safeUser` response exposes selected user fields instead of returning sensitive model attributes.

### 7.3 Policies

Policies control access to specific resources.

**EventPolicy**

- Public users can view the event listing.
- Published events can be viewed publicly.
- Admins can manage events.
- Organizers can manage their own events.
- Organizers cannot manage events owned by other organizers.

**ReservationPolicy**

- Buyers can create reservations.
- Buyers can view their own reservations.
- Admins can view reservations.
- Buyers can confirm payment for their own reservations only.

**TicketTypePolicy**

- Admins can manage ticket types.
- Organizers can manage ticket types belonging to their own events.

**Why use Policies?**

Authorization logic should not be repeated inside every controller method.

Policies centralize access rules and make them easier to maintain and test.

---

## 8. Form Requests and Validation

Form Requests are used to validate incoming data and authorize actions before the controller executes its main logic.

Implemented requests include:

### StoreEventRequest

Validates event creation data, including:

- Venue
- Title
- Description
- Event type
- Start and end dates

### UpdateEventRequest

Validates event updates and checks that the effective start and end dates are valid.

### StoreTicketTypeRequest

Validates ticket type creation, including:

- Name
- Price
- Total quantity
- Maximum tickets per order

### UpdateTicketTypeRequest

Validates ticket type updates and ensures the maximum order quantity does not exceed the effective total quantity.

### StoreReservationRequest

Validates reservation items, including:

- At least one ticket item.
- Existing ticket type IDs.
- Quantity greater than zero.

It also removes unexpected item keys before validation.

**Why use Form Requests?**

They keep validation and request authorization outside controllers.

This makes controllers easier to read and ensures invalid requests are rejected before business logic runs.

---

## 9. API Resources

API Resources control how models are converted into JSON responses.

Implemented resources:

| Resource | Responsibility |
|---|---|
| CityResource | Returns city information |
| VenueResource | Returns venue information |
| EventResource | Returns event details |
| TicketTypeResource | Returns ticket category and inventory information |
| ReservationResource | Returns reservation information |
| ReservationItemResource | Returns individual reservation items |
| TicketResource | Returns individual ticket information |

**Why use API Resources instead of returning models directly?**

Returning raw Eloquent models can expose internal database fields and create unwanted coupling between database structure and API responses.

Resources provide a controlled response structure.

For example, internal fields such as `owner_notes` or `commission_rate` should not be exposed simply because they exist in a database table.

Resources also allow relationships to be included in a consistent and predictable format.

---

## 10. Services and Business Logic

### ReservationService

The ReservationService contains the core reservation and payment logic.

It handles:

- Creating reservations.
- Combining duplicate ticket type items.
- Checking ticket availability.
- Enforcing maximum tickets per order.
- Locking ticket inventory.
- Calculating reservation totals.
- Creating ReservationItems.
- Generating individual Tickets.
- Updating reserved inventory.
- Confirming payment.
- Updating sold inventory.
- Scheduling reservation expiration.

**Why use a Service class?**

Reservation operations involve multiple database entities and business rules.

Keeping this logic in a dedicated service avoids large controllers and makes the business logic reusable and easier to test.

### EventCacheService

This service manages event cache keys and invalidation.

It uses a cache version key:

```text
events_cache_version
```

Cached event data uses versioned keys:

```text
events:v{version}:...
```

When relevant event data changes, the version is incremented.

This makes previous cache entries unreachable without needing to delete every cached event key individually.

### EventCancellationService

Handles the event cancellation workflow.

It:
- Locks the event and related ticket types.
- Prevents cancelling an already cancelled event.
- Expires pending reservations and releases reserved inventory.
- Marks paid reservations as refunded and releases sold inventory.
- Cancels tickets associated with the event.
- Dispatches cancellation notification jobs after the database transaction commits.

The service keeps cancellation logic separate from the controller and ensures related database changes are handled within one transaction.

**Important:** Refunds currently update the reservation status and inventory only. No real payment gateway refund is performed.

---

## 11. Database Transactions and Inventory Protection

Reservation creation and payment confirmation are handled inside database transactions.

This is important because each operation updates multiple related records.

### Reservation Creation

The process is:

1. Receive reservation items.
2. Combine repeated ticket type IDs.
3. Start a database transaction.
4. Lock the relevant TicketType rows.
5. Verify ticket availability.
6. Verify maximum order quantities.
7. Calculate the total using database ticket prices.
8. Create the Reservation.
9. Create ReservationItems with price snapshots.
10. Create individual Pending Tickets.
11. Increase `reserved_quantity`.
12. Commit the transaction.
13. Invalidate event cache.
14. Dispatch the expiration job.

### Payment Confirmation

The process is:

1. Start a transaction.
2. Lock the Reservation row.
3. Verify that the reservation is still Pending.
4. Verify that it has not expired.
5. Lock the relevant TicketType rows in a consistent order.
6. Verify reserved inventory.
7. Decrease `reserved_quantity`.
8. Increase `sold_quantity`.
9. Change Pending Tickets to Valid.
10. Change Reservation status to Paid.
11. Set `paid_at`.
12. Commit the transaction.

### Why use `lockForUpdate()`?

Without row locking, two buyers could attempt to reserve the last available tickets at the same time.

Database transactions and row-level locks protect inventory from race conditions and prevent overselling.

---

## 12. Events and Listeners

Laravel Events and Listeners are used to separate important application actions from the additional tasks that follow them.

### ReservationCreated

Dispatched after a reservation is successfully created.

It is used to trigger the reservation expiration workflow.

### ReservationPaid

Dispatched after payment confirmation.

It triggers the processing of paid reservation tickets.

### SendReservationConfirmation

A queued listener responsible for sending the reservation confirmation email.

It:

- Locks the reservation.
- Checks whether the email was already sent.
- Loads the buyer.
- Sends the confirmation email.
- Updates `confirmation_email_sent_at`.

### QueuePaidReservationTickets

A queued listener responsible for processing tickets belonging to a paid reservation.

It processes tickets in chunks and dispatches:

- GenerateTicketFileJob
- SendTicketSmsJob

**Why use Events and Listeners?**

Payment confirmation should not be tightly coupled to email sending, file generation, or SMS delivery.

Events allow the application to react to important actions without putting all the additional work inside ReservationService or ReservationController.

### Event Cancellation Workflow

When an event is cancelled, EventCancellationService updates the affected reservations and tickets inside a database transaction.

After the transaction commits, it dispatches SendEventCancellationNotificationJob for each affected reservation.

This keeps email delivery separate from the cancellation operation.
---

## 13. Jobs and Background Processing

Background Jobs are used for operations that do not need to block the API response.

### ExpireReservationJob

Automatically expires a pending reservation after its expiration time.

The job:

- Loads and locks the reservation.
- Checks that it is still Pending.
- Checks that its expiration time has passed.
- Locks the reservation items and ticket types.
- Releases reserved inventory.
- Cancels pending tickets.
- Updates the reservation status to Expired.
- Invalidates event cache.

The job rechecks the reservation state because the buyer may have completed payment before the job executes.

### GenerateTicketFileJob

Generates a ticket file using `TicketStorageInterface`.

It checks whether a file already exists before generating another one.

After successful storage, it updates the ticket's `file_path`.

### SendTicketSmsJob

Sends an SMS notification through `SmsServiceInterface`.

It checks `sms_sent_at` to avoid sending the same notification more than once.

It also loads the reservation buyer and verifies that a phone number exists.


### SendEventCancellationNotificationJob

Sends an email notification to buyers whose reservations are affected by an event cancellation.

It:
- Loads the reservation and buyer.
- Loads the cancelled event.
- Sends an email explaining the cancellation.
- Uses retry settings for temporary failures.
- Logs failures for troubleshooting.

The job implements ShouldQueue so notification delivery does not block the cancellation request.

### Retry Configuration

Jobs and queued listeners define retry settings such as:

```php
public $tries = 3;

public $backoff = [10, 30, 60];

public $timeout = 60;
```

This allows temporary failures to be retried instead of immediately abandoning the operation.

Failed jobs are logged for troubleshooting.

---

## 14. Reservation Expiration Workflow

Reservations have a 10-minute expiration period.

When a reservation is created, the application dispatches an `ExpireReservationJob` scheduled to run at the reservation's expiration time.

The workflow is:

```text
Buyer creates reservation
          |
          v
Reservation status = Pending
          |
          v
Reserved inventory increases
          |
          v
Expiration job is scheduled
          |
          v
Wait 10 minutes
          |
          v
Check reservation status
          |
     +----+----+
     |         |
   Paid     Pending
     |         |
     v         v
   Stop     Expire it
               |
               v
       Release inventory
               |
               v
       Cancel pending tickets
               |
               v
       Status = Expired
```

**Why use a delayed Job instead of repeatedly scanning all reservations?**

A delayed job schedules expiration for each reservation individually.

This avoids continuously scanning the entire reservations table just to find expired records.

The project also contains an `ExpireReservations` Artisan command that can process expired reservations in batches. The primary automatic expiration workflow, however, uses `ExpireReservationJob`.

---

## 15. External Services and Contracts

The application uses interfaces to separate business logic from external service implementations.

### SmsServiceInterface

Defines the SMS sending operation:

```php
send(string $phone, string $message): void
```

Current implementation:

`LogSmsService`

It logs SMS details instead of connecting to a real SMS provider.

It also supports simulated failures for testing retry behavior.

### TicketStorageInterface

Defines the ticket file storage operation:

```php
store(string $ticketCode, string $contents): string
```

Current implementation:

`LocalTicketStorage`

It stores ticket files on Laravel's local storage disk and returns the saved path.

### Why use Interfaces?

The application should depend on an abstraction rather than a specific external provider.

For example, a real SMS provider can replace `LogSmsService` without changing `SendTicketSmsJob`.

Similarly, a cloud storage implementation can replace `LocalTicketStorage` without changing the ticket generation workflow.

### Service Container Bindings

The bindings are registered in `AppServiceProvider`:

```php
SmsServiceInterface::class => LogSmsService::class

TicketStorageInterface::class => LocalTicketStorage::class
```

Laravel automatically resolves the concrete implementations when a class requests these interfaces.

---

## 16. Observers and Cache Invalidation

The application uses:

- EventObserver
- TicketTypeObserver

These observers react to model lifecycle changes.

They invalidate event cache after operations such as:

- Created
- Updated
- Deleted
- Restored
- Force deleted

The observers use after-commit behavior so cache invalidation happens after successful database transactions.

**Why use Observers?**

Event and ticket type changes can affect cached event listings and event details.

Observers centralize cache invalidation instead of requiring every controller or service to remember to invalidate the cache manually.

---

## 17. Redis and Laravel Horizon

### Redis

Redis is used as part of the application's infrastructure for caching and queue processing.

The application uses the Laravel Cache API through `EventCacheService` and supports Redis as a queue backend.

### Laravel Horizon

Horizon provides a dashboard for monitoring Redis queues and their jobs.

The configured queues include:

```text
notifications
ticket-files
default
```

These queues separate different types of background work.

For example:

- `notifications`: SMS and notification-related jobs.
- `ticket-files`: Ticket file generation.
- `default`: General queued tasks.

The Horizon configuration defines supervisors for different environments:

- Local
- Staging
- Production

The number of worker processes can be increased depending on the environment.

### Horizon Authorization

The `HorizonServiceProvider` restricts dashboard access to Admin users.

Horizon routes use the `web` middleware, so the dashboard uses Laravel's web authentication context. A Sanctum Bearer token alone is not sufficient to access the Horizon dashboard.

---

## 18. Exception Handling and API Responses

API exceptions are handled centrally in `bootstrap/app.php`.

The application returns JSON responses for API requests instead of redirecting users to HTML error pages.

### Response Structure

Successful responses and error responses use a consistent JSON structure where applicable.

Examples of handled errors:

| HTTP Status | Meaning |
|---|---|
| 401 | Unauthenticated |
| 403 | Unauthorized |
| 404 | Resource not found |
| 409 | Conflict, such as an invalid reservation state |
| 422 | Validation error |
| 500 | Unexpected server error |

### Example: Validation Error

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "items": [
            "The items field is required."
        ]
    }
}
```

### Example: Unauthorized

```json
{
    "success": false,
    "message": "This action is unauthorized."
}
```

### Example: Resource Not Found

```json
{
    "success": false,
    "message": "Resource not found."
}
```

### Example: Unexpected Error

```json
{
    "success": false,
    "message": "An unexpected error occurred."
}
```

**Why centralize exception handling?**

Without centralized handling, different controllers may return different error formats.

Centralized handling keeps the API predictable and prevents internal exception details from being exposed to clients.

---

## 19. Database Factories and Seeders

Factories and Seeders provide realistic test and development data.

### Factories

The project includes factories for:

- UserFactory
- CityFactory
- VenueFactory
- EventFactory
- TicketTypeFactory
- ReservationFactory
- ReservationItemFactory
- TicketFactory
- MediaFactory

Factories generate data using Faker and provide reusable states.

For example, `UserFactory` provides:

```php
User::factory()->admin()->create();

User::factory()->organizer()->create();

User::factory()->buyer()->create();
```

`EventFactory` provides states such as:

```php
Event::factory()->published()->create();

Event::factory()->draft()->create();

Event::factory()->cancelled()->create();
```

These states make it easier to create specific scenarios in tests.

### Seeders

The project includes:

| Seeder | Responsibility |
|---|---|
| DatabaseSeeder | Main entry point for database seeding |
| CitySeeder | Creates predefined cities |
| VenueSeeder | Creates venues |
| UserSeeder | Creates fixed users and random buyers |
| EventSeeder | Creates predefined events |
| TicketTypeSeeder | Creates ticket categories |
| MediaSeeder | Creates media records |
| BulkEventSeeder | Creates a large dataset for bulk testing |

### Why use `firstOrCreate()`?

Several seeders use `firstOrCreate()` to avoid creating duplicate records when seeders are executed more than once.

### BulkEventSeeder

This seeder creates a large event dataset with 1,000 or more ticket holders.

It uses batch inserts and database transactions to reduce database overhead.

It also synchronizes the ticket inventory values with actual paid and pending reservation records.

This helps test performance and bulk notification processing.

---

## 20. Testing

The application uses PHPUnit and Laravel's testing tools.

The test database is configured to use SQLite in-memory storage.

This means tests can run without modifying the development database.

### Main Testing Areas

The tests cover:

- Authentication.
- Role-based authorization.
- Event access.
- Reservation creation.
- Ticket inventory.
- Payment confirmation.
- Reservation expiration.
- Cache behavior.
- Jobs and listeners.
- API response structures.
- Exception handling.
- Model scopes.

### Running Tests

Run all tests:

```bash
php artisan test
```

Run a specific test:

```bash
php artisan test --filter=ReservationTest
```

Run tests with Composer:

```bash
composer test
```

The latest recorded test result during development was:

```text
156 passed
478 assertions
0 failures
```

---

## 21. API Endpoints

Base URL:

```text
http://127.0.0.1:8000/api
```

### Authentication

| Method | Endpoint | Authentication | Description |
|---|---|---|---|
| POST | `/auth/register` | Public | Register a new buyer |
| POST | `/auth/login` | Public | Login |
| POST | `/auth/logout` | Required | Logout current token |
| GET | `/auth/me` | Required | Get authenticated user |

### Events

| Method | Endpoint | Authentication | Description |
|---|---|---|---|
| GET | `/events` | Public | List published events |
| GET | `/events/{event}` | Public | Get event details |
| POST | `/events/{event}/cancel` | Required | Cancel an event |

Event listing supports filtering and pagination, including:

- Search
- City
- Event type
- Upcoming events
- Page
- Per-page limit

The per-page value is limited to a maximum of 50.

### Reservations

| Method | Endpoint | Authentication | Description |
|---|---|---|---|
| POST | `/reservations` | Required | Create a reservation |
| GET | `/reservations` | Required | List accessible reservations |
| GET | `/reservations/{reservation}` | Required | Get reservation details |
| POST | `/reservations/{reservation}/confirm-payment` | Required | Confirm payment |

### User

| Method | Endpoint | Authentication | Description |
|---|---|---|---|
| GET | `/user` | Required | Laravel authentication scaffold endpoint |

---

## 22. Running the Project Locally

### Requirements

- PHP 8.2 or higher.
- Composer.
- Node.js and npm.
- A supported database.
- Redis if using Redis queues or Redis cache.

### Installation

Clone the repository:

```bash
git clone https://github.com/ayadd22/tazkarti-ticketing-platform
```

Navigate to the project directory:

```bash
cd tazkarti
```

Install PHP dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure database credentials in `.env`.

Run migrations:

```bash
php artisan migrate
```

Seed the database:

```bash
php artisan db:seed
```

Install frontend dependencies if required by the local Laravel development setup:

```bash
npm install
npm run build
```

Start the Laravel server:

```bash
php artisan serve
```

---

## 23. Queue Configuration

Configure the queue connection in `.env` according to the intended backend.

For database queues:

```env
QUEUE_CONNECTION=database
```

For Redis queues:

```env
QUEUE_CONNECTION=redis
```

Run a queue worker when using the database queue driver:

```bash
php artisan queue:work
```

For Redis queues with Horizon:

```bash
php artisan horizon
```

Monitor failed jobs:

```bash
php artisan queue:failed
```

Retry failed jobs:

```bash
php artisan queue:retry all
```

**Important:** Horizon monitors Redis queues. If Horizon is used to process application jobs, the application's active queue connection must also be configured to use Redis. The queue connection and Horizon supervisor configuration must agree.

---

## 24. Useful Artisan Commands

| Command | Purpose |
|---|---|
| `php artisan migrate` | Run database migrations |
| `php artisan migrate:status` | Check migration status |
| `php artisan db:seed` | Run database seeders |
| `php artisan route:list --path=api` | List API routes |
| `php artisan test` | Run automated tests |
| `php artisan queue:work` | Start a queue worker |
| `php artisan queue:failed` | List failed jobs |
| `php artisan queue:retry all` | Retry failed jobs |
| `php artisan horizon` | Start Horizon |
| `php artisan horizon:status` | Check Horizon status |

---

## 25. Design Decisions

### Why API-first?

The backend is designed to serve clients through JSON APIs rather than relying on Blade pages.

This makes it easier to integrate mobile applications, web frontends, or other clients.

### Why use Services?

Complex business logic, especially reservation and inventory operations, belongs in dedicated service classes rather than controllers.

### Why use Policies?

Policies centralize authorization rules and prevent users from accessing resources they do not own.

### Why use Resources?

Resources control API output and prevent accidental exposure of internal model fields.

### Why use Transactions and Row Locks?

Reservations modify several database records. Transactions and row locks protect inventory consistency when multiple buyers reserve tickets simultaneously.

### Why use Queues?

Ticket generation, SMS delivery, and email notifications should not delay the main API response.

### Why use Events and Listeners?

They decouple the main business operation from secondary actions.

### Why use Contracts?

Interfaces make external integrations replaceable without rewriting the business logic.

### Why use Observers?

Observers centralize model-related side effects such as cache invalidation.

### Why use Redis and Horizon?

Redis provides fast cache and queue infrastructure, while Horizon provides visibility into queued jobs and their execution.

---

## 26. Current Scope and Future Improvements

The current API routes expose authentication, public event browsing, and reservation workflows.

The project also contains request classes and policies for event and ticket type operations, but those operations are not currently exposed as routes in the provided `routes/api.php`.

Potential future improvements include:

- Exposing organizer event management endpoints.
- Exposing ticket type management endpoints.
- Integrating a real payment gateway.
- Integrating a real SMS provider.
- Using cloud storage for ticket files.
- Adding ticket verification and check-in endpoints.
- Adding refund processing.
- Adding more advanced reporting and analytics.

---

## 27. Conclusion

Tazkarti demonstrates a structured Laravel API architecture with a focus on clean separation of responsibilities, secure authorization, reliable reservation workflows, inventory consistency, asynchronous processing, and maintainable external integrations.

The project combines Laravel's built-in capabilities with service classes, contracts, events, listeners, jobs, observers, policies, API resources, Redis, and Horizon to build a scalable foundation for an event ticket reservation platform.