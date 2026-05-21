# Symfony endpoints for mobile booking (SAMSON)

SAMSON calls these **JWT-protected** routes under `/api/mobile/v1`. Implemented in `src/Controller/MobileApiController.php` (logic mirrors `SubmitBookingController`).

## POST `/api/mobile/v1/bookings`

**Headers:** `Authorization: Bearer <token>`

**Body:**

```json
{
  "carId": 1,
  "name": "Customer Name",
  "phone": "09171234567",
  "pickupLocation": "Manila Airport",
  "dropoffLocation": "Manila Airport",
  "pickupDate": "2026-06-01",
  "returnDate": "2026-06-05",
  "pickupTime": "9:00 AM",
  "returnTime": "5:00 PM"
}
```

**Response envelope `data`:**

```json
{
  "booking": {
    "id": 42,
    "status": "Pending",
    "name": "Customer Name",
    "pickupDate": "2026-06-01",
    "returnDate": "2026-06-05",
    "pickupTime": "09:00:00",
    "returnTime": "17:00:00",
    "pickupLocation": "Manila Airport",
    "dropoffLocation": "Manila Airport",
    "car": { "id": 1, "brand": "Toyota", "model": "Corolla" }
  }
}
```

## GET `/api/mobile/v1/bookings`

Returns only the logged-in customer's bookings (`BookingRepository::findForCustomer`).

**Response `data`:**

```json
{
  "bookings": []
}
```

## POST `/api/mobile/v1/bookings/check-conflict`

Same body as create (optional `excludeBookingId`). Returns `{ "hasConflict": false, "message": "" }`.

## Security

In `config/packages/security.yaml`:

- `^/api/mobile/v1/bookings` → `ROLE_USER` (JWT)
- `^/api/mobile/v1/health` and `^/api/mobile/v1/cars` → public

## Cars

`serializeCar()` includes `imageUrl` from `CarPhotoUploadService::resolvePublicUrl()`.
