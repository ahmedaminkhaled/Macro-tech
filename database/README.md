# Step 1 - Database setup

## Files
- `schema.sql`: creates database, tables, indexes, foreign keys, checks, and triggers.
- `seed.sql`: inserts default categories and sample products.

## Import order (phpMyAdmin)
1. Create/import `schema.sql`
2. Create/import `seed.sql`

## Notes
- Database name: `store_app_db`
- Engine: InnoDB
- Charset: utf8mb4
- Core constraints are enforced at DB level:
  - category unique name (case-insensitive)
  - product reference immutable
  - product delete only when quantity = 0
  - stock cannot go negative during sale operations
