# Product Import Service

This repository contains a Laravel 12 application focused on ingesting large product catalogs efficiently. It supports two complementary import modes:

- **Legacy CSV importer** – stream a flat CSV with thousands of rows using a generator, chunking, and upserts for minimal memory usage.
- **Warehouse-aware importer** – accept richer payloads (attributes, variations, warehouses) and process them through staged records, queued batches, and relation syncing to keep multiple systems in sync.

## Table of contents
- [System overview](#system-overview)
- [Local installation](#local-installation)
  - [Docker (Laravel Sail)](#docker-laravel-sail)
  - [CEL shortcut](#cel-shortcut)
- [Legacy CSV importer](#legacy-csv-importer)
- [Warehouse-aware importer](#warehouse-aware-importer)
  - [Data flow](#data-flow)
  - [Expected payload shape](#expected-payload-shape)
  - [Running the importer](#running-the-importer)
  - [Monitoring and recovery](#monitoring-and-recovery)
- [Database schema](#database-schema)
- [Queues](#queues)

## System overview
- **Command-based ingestion**: Artisan commands drive both the legacy CSV import (`app:import-products-old`) and the enhanced relational import (`app:import-products`).【F:app/Console/Commands/ImportProductsOldStructure.php†L17-L78】【F:app/Console/Commands/ImportProducts.php†L17-L76】
- **Streaming CSV reader**: `ImportProductService::getRecords()` yields one CSV row at a time to avoid loading the full file into memory.【F:app/Services/ImportProductService.php†L43-L72】
- **Chunked upserts**: Products are buffered in configurable chunk sizes and upserted, minimizing lock contention and enabling fast bulk imports.【F:app/Console/Commands/ImportProductsOldStructure.php†L37-L70】【F:app/Services/ImportProductService.php†L13-L25】
- **Relation syncing**: Product variations, attributes, options, and warehouse inventories are synchronized through `ProductRelationSyncService`, which rebuilds relations inside a transaction for consistency.【F:app/Services/ProductRelationSyncService.php†L17-L108】
- **Staging + batching**: The warehouse-aware importer stores raw payloads in staging tables, normalizes them, and dispatches queue batches per product so multiple integrations (webhooks, notifications, inventory) run in parallel.【F:app/Services/ProductSyncService.php†L17-L120】

## Local installation

### Docker (Laravel Sail)
Laravel Sail is preconfigured via `compose.yaml` to provide PHP-FPM, MySQL 8, and Redis containers.【F:compose.yaml†L1-L63】

1. **Copy environment file**
   ```bash
   cp .env.example .env
   ```
2. **Build the Docker images** (first run only)
   ```bash
   ./vendor/bin/sail build
   ```
3. **Install dependencies**
   ```bash
   ./vendor/bin/sail composer install
   ./vendor/bin/sail npm install
   ```
4. **Start the stack**
   ```bash
   ./vendor/bin/sail up -d
   ```
5. **Generate the app key & run migrations**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate
   ```
6. **Run queues**
   ```bash
   ./vendor/bin/sail artisan queue:work --queue=product-process,default
   ```
   Use `./vendor/bin/sail artisan horizon` if you prefer Horizon’s dashboard (Horizon is installed).【F:composer.json†L12-L21】

### CEL shortcut
If your environment provides the `cel` alias as a wrapper around Sail, replace `./vendor/bin/sail` in the commands above with `cel` (for example, `cel up -d` or `cel artisan migrate`). The underlying containers and services are identical.

## Legacy CSV importer
The legacy flow is optimized for very large CSV exports (thousands of products) and focuses on raw product rows.

1. **Place your CSV** at `storage/products.csv`. The header row should include: `id,name,sku,status,price,currency,variations,warehouses`.
2. **Run the command**
   ```bash
   php artisan app:import-products-old --chunk=500
   # or inside Sail/CEL: ./vendor/bin/sail artisan app:import-products-old --chunk=1000
   ```
3. **What happens**
   - Rows are streamed via `getRecords()` (generator) to avoid memory spikes.【F:app/Services/ImportProductService.php†L43-L72】
   - Each chunk is upserted into `products` with timestamps pre-set to the run start, keeping inserts/updates idempotent.【F:app/Console/Commands/ImportProductsOldStructure.php†L37-L70】【F:app/Services/ImportProductService.php†L13-L25】
   - Optional relation syncing (attributes/options/warehouses) is available through `syncAllProductsRelations()` if you uncomment the calls in the command.【F:app/Console/Commands/ImportProductsOldStructure.php†L48-L69】
   - Products not touched during the run (older `created_at`/`updated_at`) are cleaned up to remove stale rows.【F:app/Console/Commands/ImportProductsOldStructure.php†L71-L76】

Use `--chunk` to tune performance. Smaller chunks reduce lock time; larger chunks improve throughput on fast disks/DB hosts.

## Warehouse-aware importer
This flow is designed for richer e-commerce payloads (attributes, attribute values, variations, warehouses) and runs everything asynchronously so multiple large imports can progress concurrently.

### Data flow
1. **Fetch** – `FetchProductsJob` resolves a `FetchProductsServiceInterface` implementation for the requested source and fetches products, then dispatches `ProductFetchedEvent` with the raw JSON payload.【F:app/Jobs/FetchProductsJob.php†L5-L26】【F:app/Events/ProductFetchedEvent.php†L13-L19】
2. **Stage** – `ProductFetched` listener persists the payload into `staging_products` and dispatches `ProcessProductJob` for that batch.【F:app/Listeners/ProductFetched.php†L12-L23】【F:app/Services/ProductSyncService.php†L17-L32】
3. **Normalize** – `ProcessProductJob` calls `ProductSyncService::syncFromExternalApi()` which converts each raw product into a `NormalizedProductDTO` (building SKUs, attributes, variations, and empty warehouse shells).【F:app/Jobs/ProcessProductJob.php†L15-L38】【F:app/Services/ProductSyncService.php†L34-L120】【F:app/DTOs/NormalizedProductDTO.php†L7-L52】
4. **Batch processing** – For every product, a queue batch runs integrations (`NotifyCustomerBackToStockJob`, `YallaConnectWebhookJob`, `IntegrateWithYallaBuyInventoryManagementJob`). After the batch succeeds, the product is upserted and relations are rebuilt via `ProductRelationSyncService`. Failures mark the staging item as failed and stop the staging batch.【F:app/Services/ProductSyncService.php†L61-L115】
5. **Progress tracking** – `staging_products` and `staging_product_items` tables track totals, processed counts, status, and timestamps for observability and retries.【F:database/migrations/2025_11_23_150640_create_staging_products_table.php†L13-L23】【F:database/migrations/2025_11_24_162000_create_staging_product_items_table.php†L13-L24】

### Expected payload shape
Each raw product supplied to the importer should resemble:

```json
{
  "id": 123,
  "name": "Product name",
  "price": 49.99,
  "variations": [
    {"color": "red", "material": "cotton", "additional_price": 0},
    {"color": "blue", "material": "linen", "additional_price": 10}
  ],
  "isDeleted": false
}
```

The normalizer builds a base SKU from the name + ID and expands each variation into a concrete SKU with option mappings (`color`, `material`). Warehouses are initialized as an empty set and can be populated in downstream jobs or by extending the DTO.【F:app/Services/ProductSyncService.php†L116-L139】【F:app/DTOs/NormalizedProductDTO.php†L33-L52】

### Running the importer
Triggering the warehouse-aware pipeline typically involves dispatching the fetch job for a source key:

```bash
php artisan tinker
>>> FetchProductsJob::dispatch('default-source');
```

Ensure queue workers are running with access to the `product-process` and `default` queues:

```bash
./vendor/bin/sail artisan queue:work --queue=product-process,default
```

If you already have raw JSON from an external system, you can bypass the fetch job by calling `ProductSyncService::storeDataIntoStagingTable($json)` from a controller, command, or Tinker session to seed the staging tables directly.【F:app/Services/ProductSyncService.php†L17-L32】

### Monitoring and recovery
- **Progress**: Inspect `staging_products` and `staging_product_items` for counts, statuses, and timestamps to verify batches are advancing.【F:database/migrations/2025_11_23_150640_create_staging_products_table.php†L13-L23】【F:database/migrations/2025_11_24_162000_create_staging_product_items_table.php†L13-L24】
- **Retries**: Horizon or `queue:retry` can replay failed jobs; staging item statuses are updated automatically on success/failure so you can detect stuck items.【F:app/Services/ProductSyncService.php†L61-L115】
- **Clean-up**: The legacy command removes products not touched in the current run; the warehouse-aware flow leaves history in staging tables for auditability.【F:app/Console/Commands/ImportProductsOldStructure.php†L71-L76】【F:app/Services/ProductSyncService.php†L61-L115】

## Database schema
Key tables created by the migrations include:

- `products` (core product data, soft deletes enabled).【F:app/Models/Product.php†L6-L31】
- `product_attributes`, `product_attribute_values`, `product_options`, `product_variations`, `product_option_variations` (attribute/value/option modeling for variations).【F:app/Services/ProductRelationSyncService.php†L19-L108】
- `warehouses`, `warehouse_inventories` (inventory per variation per location).【F:app/Services/ProductRelationSyncService.php†L79-L107】
- `staging_products`, `staging_product_items` (staged import tracking).【F:database/migrations/2025_11_23_150640_create_staging_products_table.php†L13-L23】【F:database/migrations/2025_11_24_162000_create_staging_product_items_table.php†L13-L24】

Run `./vendor/bin/sail artisan migrate` to create all tables before importing.

## Queues
- **Queues used**: `product-process` (staging processor) and `default` (batch jobs).
- **Batching**: Each product spawns a batch of integration jobs; once completed, the product row and relations are committed in a transaction to prevent partial writes.【F:app/Services/ProductSyncService.php†L61-L115】
- **Workers**: Keep workers up with `./vendor/bin/sail artisan queue:work --queue=product-process,default` or `./vendor/bin/sail artisan horizon` for monitored processing.【F:composer.json†L12-L21】【F:app/Jobs/ProcessProductJob.php†L15-L26】
