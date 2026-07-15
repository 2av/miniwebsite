# API Conventions

## Base URL

`/api/v1/{resource}`

## Response envelope

Success:

```json
{ "success": true, "message": optional, "data": { } }
```

Failure:

```json
{ "success": false, "message": "...", "errors": { "Field": ["..."] } }
```

## CRUD verbs

| Method | Route | Meaning |
|--------|-------|---------|
| GET | `/items` | Paged list |
| GET | `/items/{id}` | Detail |
| POST | `/items` | Create |
| PUT/PATCH | `/items/{id}` | Update |
| DELETE | `/items/{id}` | Soft delete preferred |

## Money

Amounts in **rupees** as `decimal` with 2 places in API contracts. Payment gateway may convert to paise internally.

## Auth (from Phase 1)

`Authorization: Bearer {access_token}`

Roles: `Customer`, `Franchisee`, `Team`, `Admin`
