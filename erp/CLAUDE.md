# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development

```bash
composer run dev        # Starts all services concurrently: PHP server, queue worker, log tail (Pail), and Vite
composer run setup      # First-time setup: install deps, copy .env, generate key, migrate, build assets
```

### Testing

```bash
composer run test                                        # Clear config cache then run full test suite
php artisan test --filter ServiceOrderWorkflowTest       # Run a single test class
php artisan test tests/Feature/FinancialWorkflowTest.php # Run a specific file
```

### Other useful commands

```bash
php artisan migrate --seed          # Migrate and seed with demo data
php artisan db:seed --class=MockDataSeeder
php artisan service-orders:check-sla  # Check OS SLA violations (normally scheduled)
php artisan pint                    # Run Laravel Pint code formatter
```

## Infrastructure

- **Database**: PostgreSQL 16 (default DB name: `neksa_erp`). Docker Compose via `docker-compose.yml` (Laravel Sail).
- **Cache/Queue**: Redis. Queue connection defaults to `database` in `.env`, but tests use `sync`.
- **Frontend**: Blade templates + Alpine.js + Tailwind CSS v3 + Livewire v4 + Vite.
- **PDF**: `barryvdh/laravel-dompdf` — templates under `resources/views/pdf/`.
- **XLSX export**: `phpoffice/phpspreadsheet` — via `ExportXlsxService`.
- **Roles/permissions**: `spatie/laravel-permission`. The `admin` role bypasses all gates via `Gate::before` in `AppServiceProvider`.
- **Activity log**: `spatie/laravel-activitylog`.
- Tests use an in-memory SQLite database (`DB_DATABASE=testing` with `DB_URL=` left blank).

## Architecture Overview

This is an ERP system for field service companies (Neksa ERP). The main business entity is the **Service Order (OS)**. All other modules revolve around it.

### Module Map

| Module | Controllers | Key models |
|---|---|---|
| Clients | `ClientController`, `ClientEquipmentController` | `Client`, `ClientAddress`, `ClientContact`, `ClientEquipment`, `Cnae` |
| Service Orders | `ServiceOrderController`, `ServiceOrderOperationsController` | `ServiceOrder`, `ServiceOrderItem`, `ServiceOrderStatus`, `ServiceOrderStatusHistory`, `ServiceOrderHistory`, `ServiceOrderChecklist`, `ServiceOrderCheckin`, `ServiceOrderSignature` |
| Quotes | `QuoteController` | `Quote`, `QuoteItem` |
| Sales | `SaleController` | `Sale`, `SaleItem`, `SalePayment`, `SaleAttachment` |
| Products/Services | `ProductController`, `ServiceController` | `Product`, `Service` |
| Suppliers & Purchasing | `SupplierController`, `PurchaseOrderController`, `InventoryConferenceController`, `XmlImportController` | `Supplier`, `PurchaseOrder`, `PurchaseOrderItem`, `StockMovement`, `InventoryConference` |
| Financial | `ReceivableController`, `PayableController`, `CashFlowController`, `FinancialAccountController` | `Receivable`, `ReceivableInstallment`, `Payable`, `PayableInstallment`, `FinancialAccount`, `FinancialEvent` |
| Routes | `RouteController` | `Route`, `RouteServiceOrder` |
| Admin settings | `Admin\ServiceOrderStatusController`, `Admin\ChecklistTemplateController` | `ServiceOrderStatus`, `ChecklistTemplate`, `ChecklistQuestion` |

There is also a REST API under `Api\V1\` (Sanctum-authenticated) exposing `ClientController` and `ServiceOrderController`.

### Service Layer

Business logic lives in `app/Services/` rather than controllers:

- **`ServiceOrderService`** — create, update, `changeStatus`, `assignTechnician`, `checkIn`, `duplicate`. Status transitions trigger: financial receivable creation/cancellation, stock deduction/reversal, and domain events.
- **`FinancialService`** — `createReceivable`, `cancelReceivable`, `createPayable`, `cancelPayable`, and installment payment.
- **`StockMovementService`** — wraps all inventory movements with negative-stock guard (configurable per company).
- **`SaleService`** — complete/cancel sales with financial and stock side effects.
- **`PurchaseOrderService`** — purchase order approval and stock input on receipt.
- **`ServiceOrderChecklistService`** — syncs required checklists when an OS is created/updated.
- **`CnpjaService`** — external CNPJ lookup API for auto-filling PJ client data.
- **`RouteOptimizationService`** — builds optimized technician routes from service orders.

There is one Action class: **`ConvertQuoteAction`** (`app/Actions/`) handles Quote → Sale or Quote → ServiceOrder conversion with business-rule validation (Sales cannot contain Services; OS must have at least one Service).

### Service Order Status Machine

Statuses are database-driven (`service_order_statuses` table, managed via `settings/statuses`). Each status can define:
- Allowed transitions (`service_order_status_transitions` pivot)
- `is_completed_state` / `is_cancelled_state` flags
- `max_stay_minutes` for SLA alerts

Completing an OS requires: all checklists filled, all required answers answered, client signature collected, and at least one check-in.

The OS has **two parallel history systems** (both written on every state change):
1. `service_order_status_history` — structured SLA-aware entries with `entered_at`/`left_at`/`duration_minutes`.
2. `service_order_histories` — free-text timeline log for display purposes.

### Financial Integration

Completing an OS auto-generates a `Receivable` with installments derived from `ServiceOrderPayment` records. Cancelling an OS that has an unpaid `Receivable` cancels it; cancellation is blocked if any installment has `paid_amount > 0`.

Completing a `Sale` follows the same pattern. Purchase order receipt creates a `Payable`.

Document codes follow the pattern: `OS-{YEAR}-{seq:05}`, `REC-{YEAR}-{seq:06}`, `PAY-{YEAR}-{seq:06}`.

### Multi-tenancy

The app is single-tenant. `Company::first()` is shared to all views via `View::share('tenantCompany', ...)` in `AppServiceProvider::boot()`. Company settings (e.g. `allow_negative_stock`) affect stock guard behavior.

### Policies

`ClientPolicy` and `ServiceOrderPolicy` are registered in `AppServiceProvider`. All other policies (`QuotePolicy`, `SalePolicy`, `ProductPolicy`, etc.) are registered automatically by Laravel's convention. The `admin` role bypasses all policy checks.
