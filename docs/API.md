# CargoTrace REST API (so far)

All routes are under `/api`. Send `Accept: application/json`. Authenticated routes need
`Authorization: Bearer <token>` (token from `POST /api/login`).

| Method | Path | Roles | Notes |
|---|---|---|---|
| POST | /register | public | always creates a `customer` |
| POST | /login | public | returns `data.token` |
| POST | /logout | any | revokes the current token |
| GET | /user | any | profile |
| GET | /carriers, /carriers/{id} | admin, operator | `?search=&is_active=&per_page=` |
| POST/PUT/DELETE | /carriers... | admin | 409 if carrier has shipments |
| GET | /locations, /locations/{id} | admin, operator | `?search=&region=&type=&country_code=` |
| POST/PUT/DELETE | /locations... | admin | 409 if used by a shipment |
| GET | /shipments | any | customers only see their own. Filters: `search, status, scenario, is_delayed, carrier_id, customer_id, origin_location_id, destination_location_id, arrival_from, arrival_to, sort, direction, per_page` |
| GET | /shipments/{id} | any (own for customer) | includes items and current location |
| POST | /shipments | admin, operator | creates shipment + items + first event |
| PUT/PATCH | /shipments/{id} | admin, operator | only while CREATED or BOOKED |
| POST | /shipments/{id}/cancel | admin, operator | only while CREATED or BOOKED |
| DELETE | /shipments/{id} | admin | soft delete, only CREATED or CANCELLED |
| GET | /shipments/{id}/tracking | any (own for customer) | timeline, oldest first |
| POST | /shipments/{id}/tracking-events | admin, operator | moves the shipment forward |

## Status lifecycle
`CREATED -> BOOKED -> PICKED_UP -> IN_TRANSIT -> DELIVERED` (CANCELLED possible from CREATED / BOOKED).

| Event type | Effect |
|---|---|
| BOOKED | CREATED -> BOOKED |
| PICKED_UP | BOOKED -> PICKED_UP |
| DEPARTED_PORT | PICKED_UP -> IN_TRANSIT, sets actual departure, shifts ETA if departure was late |
| TRANSSHIPMENT, IN_TRANSIT, ARRIVED_PORT, CUSTOMS_CLEARANCE | only while IN_TRANSIT, status unchanged |
| DELIVERED | IN_TRANSIT -> DELIVERED, sets actual arrival and final delay |

Error codes: 401 not logged in, 403 role/ownership, 404 unknown or soft-deleted, 409 conflicts with
current state (illegal transition, closed shipment), 422 invalid data (validation, event out of order).

## Delay rule
`delay = estimated arrival - expected arrival` (minutes, never negative). Delayed when delay > 0.
Severity: LOW <= threshold/2, MEDIUM <= threshold*2, HIGH above. Threshold per scenario
(A 12h, B 18h, C 24h) in `config/cargotrace.php`.
