What this is

A tiny Slim PHP service that accepts a booking “rate check” payload, mutates it into the format Gondwana’s dev endpoint expects, posts it, then relays the response back to the caller plus a handy summary block for the UI.

I kept it boring-simple: clear validation, small helpers, no magic.

Endpoints
POST /api/rates

Check rates & availability.

Request (two supported input styles)

A) Counts (preferred by the new frontend)
The API synthesizes ages and guest groups for you.

{
"Unit Type ID": -2147483637,
"Arrival": "2025-09-27",
"Departure": "2025-09-28",
"Adults": 1,
"Kids 6-13": 1,
"Kids 0-5": 0
}

B) Explicit Ages (legacy mode)
If you already have the ages, you can send them.

{
"Unit Name": "Desert Lodge Family Room",
"Arrival": "27/09/2025",
"Departure": "28/09/2025",
"Occupants": 2,
"Ages": [36, 9]
}

Notes:

Unit Name maps to Unit Type ID via Config/config.php. You can also pass Unit Type ID directly.

Dates: accepts dd/mm/yyyy or yyyy-mm-dd. The API normalizes to yyyy-mm-dd for the remote call.

Response

The API relays both the transformed payload and the remote response, with a computed summary the frontend can show without spelunking JSON:

{
"request": {
"received": { /_ your input _/ },
"transformed": {
"Unit Type ID": -2147483637,
"Arrival": "2025-09-27",
"Departure": "2025-09-28",
"Guests": [{ "Age Group": "Adult" }, { "Age Group": "Child" }]
}
},
"remote": {
"status": 200,
"body": { /_ passthrough from dev.gondwana endpoint _/ }
},
"summary": {
"availability": true, // derived (see below)
"rooms": 7, // remote "Rooms"
"totalCharge": 48750, // remote "Total Charge"
"effectiveDailyMin": 16250, // min of each leg's "Effective Average Daily Rate"
"unitTitle": "Kalahari Farmhouse",// parsed from "Special Rate Description"
"unitTypeId": -2146822694, // remote "Booking Client ID" (acts like unit type id)
"arrival": "2025-09-27",
"departure": "2025-09-28"
}
}

How summary gets computed

rooms: remote Rooms

availability: rooms > 0 (fallbacks to null if missing)

effectiveDailyMin: min of all legs’ Effective Average Daily Rate (or null)

Unit name/title: parsed from Special Rate Description (e.g., “… – Kalahari Farmhouse”)

Unit type id: remote Booking Client ID (per samples you provided)

Quick start
Prereqs

PHP 8.1+

Composer

Install & Run
cd api
composer install
cp .env.example .env
php -S 0.0.0.0:8000 -t public

Environment

.env keys (with sane defaults):

REMOTE_RATES_URL=https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php
REMOTE_TRANSPORT=json # or "form"
ADULT_AGE=12 # age cutoff for adult vs child when using explicit Ages

Config/config.php also includes the unit-name→id map used in legacy mode:

'unit_name_to_type_id' => [
'Desert Lodge Family Room' => -2147483637,
'Desert Lodge Twin' => -2147483456,
],

Curl tests

Counts mode

curl -sX POST http://localhost:8000/api/rates \
 -H 'Content-Type: application/json' \
 -d '{
"Unit Type ID": -2147483637,
"Arrival": "2025-09-27",
"Departure": "2025-09-28",
"Adults": 1,
"Kids 6-13": 1,
"Kids 0-5": 0
}' | jq

Explicit ages mode

curl -sX POST http://localhost:8000/api/rates \
 -H 'Content-Type: application/json' \
 -d '{
"Unit Name": "Desert Lodge Family Room",
"Arrival": "27/09/2025",
"Departure": "28/09/2025",
"Occupants": 2,
"Ages": [36, 9]
}' | jq

Project layout (high level)
app/
Controllers/RatesController.php # validation → transform → remote call → response
Services/RemoteRateClient.php # Guzzle client
Transformers/PayloadTransformer.php# date & guests conversion
Validators/RequestValidator.php # payload checks
Config/config.php # env + mapping
public/index.php # Slim bootstrap

Error handling: validation returns 422 with an errors[] array; remote failures are surfaced as 502 with a short diagnostic payload.

Why it’s built this way

Stable contracts: the frontend should not need to know how to parse “Booking Client ID” into a unit id or how to extract rates from “Legs.” The API does that and returns a clear summary.

Testable: you can stub the remote and exercise the transformer/validator without network calls.

Debuggable: the request.transformed echo tells you exactly what we sent upstream.

If this needs to survive a sandstorm, we can add retries, circuit-breaking, and better schema validation—but this gets the assignment done cleanly.
