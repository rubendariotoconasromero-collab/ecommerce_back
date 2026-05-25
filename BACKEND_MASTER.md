# BACKEND MASTER — Ecommerce Codesoft (SOLUPLAST)

> Documento de referencia técnica completo. Generado el 2026-05-24.  
> Stack: **Laravel 12** · **MySQL** · **Laravel Sanctum** · **UUID** primary keys en todas las entidades de negocio.

---

## Índice

1. [Stack y Configuración](#1-stack-y-configuración)
2. [Esquema de Base de Datos](#2-esquema-de-base-de-datos)
3. [Modelos Eloquent](#3-modelos-eloquent)
4. [Sistema de Autenticación y RBAC](#4-sistema-de-autenticación-y-rbac)
5. [API REST — Referencia Completa](#5-api-rest--referencia-completa)
6. [Capa de Servicios](#6-capa-de-servicios)
7. [Máquinas de Estado](#7-máquinas-de-estado)
8. [Reglas de Negocio Críticas](#8-reglas-de-negocio-críticas)
9. [Almacenamiento de Archivos](#9-almacenamiento-de-archivos)
10. [Seeders y Datos Iniciales](#10-seeders-y-datos-iniciales)
11. [Gaps y Pendientes](#11-gaps-y-pendientes)

---

## 1. Stack y Configuración

| Componente         | Detalle                                      |
|--------------------|----------------------------------------------|
| Framework          | Laravel 12                                   |
| PHP                | >= 8.2                                       |
| Base de datos      | MySQL (Laragon local)                        |
| Autenticación      | Laravel Sanctum (tokens de API stateless)    |
| Primary keys       | UUID en todas las tablas de negocio          |
| Paginación default | 15–20 registros por página (varía por módulo)|
| Formato de respuesta | JSON · Laravel API Resources               |
| Almacenamiento     | Laravel Storage (`storage/app/public/`)      |
| URL Base API       | `http://localhost:8000/api`                  |

**Middleware global de autenticación:** `auth:sanctum` en todas las rutas protegidas.  
**Token:** Bearer token en header `Authorization: Bearer {token}`.  
**Token almacenado en:** `localStorage('access_token')` en el frontend.

---

## 2. Esquema de Base de Datos

### Diagrama de relaciones (resumen)

```
permissions ←─── role_permission ───→ roles ←─── users
                                                    │
                                              ┌─────┘
                                              ↓
customers ──────────────────────────────→ orders
    │                                        │
    │                               ┌────────┼────────────────┐
    │                               ↓        ↓                ↓
    │                          order_items  payments     order_returns
    │                               │
    │                    ┌──────────┘
    │                    ↓
    │             production_orders
    │
    └── user_id (opcional) → users

orders ──→ shipments
orders ──→ order_handlers

products ──→ inventory (1:1)
products ──→ products_images (1:N)
products ──→ categories (N:1)
categories ──→ categories (auto-referencia parent_id)
```

---

### 2.1 Tabla: `permissions`

| Columna    | Tipo          | Restricciones          |
|------------|---------------|------------------------|
| id         | UUID          | PK                     |
| name       | varchar(100)  | NOT NULL               |
| slug       | varchar(100)  | UNIQUE, NOT NULL       |
| module     | varchar(100)  | NOT NULL               |
| is_active  | boolean       | DEFAULT true           |
| created_at | timestamp     |                        |
| updated_at | timestamp     |                        |

---

### 2.2 Tabla: `roles`

| Columna     | Tipo          | Restricciones    |
|-------------|---------------|------------------|
| id          | UUID          | PK               |
| name        | varchar(100)  | NOT NULL         |
| slug        | varchar(100)  | UNIQUE, NOT NULL |
| description | text          | nullable         |
| is_active   | boolean       | DEFAULT true     |
| created_at  | timestamp     |                  |
| updated_at  | timestamp     |                  |

---

### 2.3 Tabla: `role_permission` (pivot N:M)

| Columna       | Tipo | Restricciones                    |
|---------------|------|----------------------------------|
| role_id       | UUID | FK → roles.id, CASCADE DELETE    |
| permission_id | UUID | FK → permissions.id, CASCADE DELETE |
| created_at    | timestamp | DEFAULT CURRENT_TIMESTAMP   |

PK compuesta: `(role_id, permission_id)`

---

### 2.4 Tabla: `users`

| Columna        | Tipo         | Restricciones                  |
|----------------|--------------|--------------------------------|
| id             | UUID         | PK                             |
| role_id        | UUID         | FK → roles.id                  |
| name           | varchar(150) | NOT NULL                       |
| email          | varchar(150) | UNIQUE, NOT NULL               |
| password       | varchar      | NOT NULL (hashed bcrypt)       |
| phone          | varchar(30)  | nullable                       |
| is_active      | boolean      | DEFAULT true                   |
| remember_token | varchar(100) | nullable (Laravel standard)    |
| created_at     | timestamp    |                                |
| updated_at     | timestamp    |                                |
| deleted_at     | timestamp    | nullable — SoftDeletes activo  |

> **Nota:** `users` es la única tabla con SoftDeletes de Laravel (`deleted_at`). Todas las demás entidades usan desactivación lógica via `is_active`.

---

### 2.5 Tabla: `categories`

| Columna     | Tipo         | Restricciones                              |
|-------------|--------------|--------------------------------------------|
| id          | UUID         | PK                                         |
| parent_id   | UUID         | nullable, FK → categories.id, SET NULL     |
| name        | varchar(100) | NOT NULL                                   |
| slug        | varchar(100) | UNIQUE, NOT NULL                           |
| description | text         | nullable (añadido en migración posterior)  |
| image_url   | varchar      | nullable (añadido en migración posterior)  |
| is_active   | boolean      | DEFAULT true                               |
| created_at  | timestamp    |                                            |
| updated_at  | timestamp    |                                            |

> Soporta jerarquía de categorías mediante `parent_id` auto-referencial.

---

### 2.6 Tabla: `products`

| Columna                   | Tipo             | Restricciones               |
|---------------------------|------------------|-----------------------------|
| id                        | UUID             | PK                          |
| category_id               | UUID             | FK → categories.id, RESTRICT|
| sku                       | varchar(100)     | UNIQUE, NOT NULL            |
| name                      | varchar(200)     | NOT NULL                    |
| description               | text             | nullable                    |
| base_price                | decimal(12,2)    | NOT NULL                    |
| cost_price                | decimal(12,2)    | NOT NULL (precio de costo)  |
| sale_price                | decimal(12,2)    | NOT NULL (precio de venta)  |
| production_lead_time_days | smallint         | DEFAULT 0                   |
| attributes                | json             | nullable                    |
| is_active                 | boolean          | DEFAULT true                |
| is_featured               | boolean          | DEFAULT false               |
| created_at                | timestamp        |                             |
| updated_at                | timestamp        |                             |

> `cost_price` se usa internamente para calcular `line_profit` en órdenes. No se expone al frontend público.  
> `attributes` almacena metadatos flexibles en JSON (ej: `{"material":"PET","color":"Varios","reciclable":true}`).

---

### 2.7 Tabla: `products_images`

| Columna    | Tipo         | Restricciones                    |
|------------|--------------|----------------------------------|
| id         | UUID         | PK                               |
| product_id | UUID         | FK → products.id, CASCADE DELETE |
| image_path | varchar(500) | NOT NULL (ruta en storage)       |
| is_primary | boolean      | DEFAULT false                    |
| sort_order | smallint     | DEFAULT 0                        |
| created_at | timestamp    | DEFAULT CURRENT_TIMESTAMP        |

> No tiene `updated_at`. Solo se crea o elimina una imagen, no se edita.

---

### 2.8 Tabla: `company_settings`

| Columna            | Tipo     | Descripción                         |
|--------------------|----------|-------------------------------------|
| id                 | bigint   | PK auto-increment (no UUID)         |
| company_name       | varchar  | nullable                            |
| email              | varchar  | nullable                            |
| phone              | varchar  | nullable                            |
| address            | text     | nullable                            |
| facebook_url       | varchar  | nullable                            |
| instagram_url      | varchar  | nullable                            |
| whatsapp           | varchar  | nullable (número sin formato)       |
| hero_title         | varchar  | nullable (landing page)             |
| hero_subtitle      | varchar  | nullable (landing page)             |
| hero_image_path    | varchar  | nullable                            |
| about_title        | varchar  | nullable (sección nosotros)         |
| about_description  | text     | nullable                            |
| about_image_path   | varchar  | nullable                            |
| footer_text        | varchar  | nullable                            |
| created_at         | timestamp|                                     |
| updated_at         | timestamp|                                     |

> Siempre existe **un único registro** (id = 1). Se hace `updateOrCreate` en el seeder.

---

### 2.9 Tabla: `customers`

| Columna       | Tipo                       | Restricciones                           |
|---------------|----------------------------|-----------------------------------------|
| id            | UUID                       | PK                                      |
| user_id       | UUID                       | nullable, FK → users.id, SET NULL       |
| customer_type | ENUM('individual','business') | DEFAULT 'individual'               |
| name          | varchar(150)               | NOT NULL (nombre de contacto principal) |
| email         | varchar(150)               | UNIQUE, NOT NULL                        |
| business_name | varchar(150)               | nullable (solo para `business`)         |
| tax_id        | varchar(50)                | nullable (NIT / RUC / RFC)              |
| phone         | varchar(30)                | nullable                                |
| is_active     | boolean                    | DEFAULT true                            |
| created_at    | timestamp                  |                                         |
| updated_at    | timestamp                  |                                         |

> `user_id` vincula opcionalmente el perfil de cliente con una cuenta de acceso al sistema.  
> `business_name` es **obligatorio** cuando `customer_type = 'business'` (validado en Request).

---

### 2.10 Tabla: `inventory`

| Columna           | Tipo    | Restricciones                         |
|-------------------|---------|---------------------------------------|
| id                | UUID    | PK                                    |
| product_id        | UUID    | UNIQUE, FK → products.id, CASCADE     |
| qty_available     | integer | DEFAULT 0                             |
| qty_reserved      | integer | DEFAULT 0                             |
| qty_in_production | integer | DEFAULT 0                             |
| reorder_point     | integer | DEFAULT 0                             |
| updated_at        | timestamp | Gestionado por MySQL (ON UPDATE)   |

> **No tiene `created_at`**. Solo `updated_at`.  
> Relación 1:1 con `products` (UNIQUE en product_id).  
> El registro se crea automáticamente al crear un producto (via `InventoryService::initForProduct()`).

**Atributos calculados (no en BD):**
- `qty_sellable = qty_available - qty_reserved` (stock efectivamente disponible para venta)
- `is_low_stock = qty_sellable <= reorder_point`

---

### 2.11 Tabla: `orders`

| Columna                | Tipo                                                                               | Restricciones                      |
|------------------------|------------------------------------------------------------------------------------|------------------------------------|
| id                     | UUID                                                                               | PK                                 |
| customer_id            | UUID                                                                               | FK → customers.id                  |
| user_id                | UUID                                                                               | nullable, FK → users.id, SET NULL  |
| total_amount           | decimal(12,2)                                                                      | NOT NULL (calculado en servidor)   |
| status                 | ENUM('pending','confirmed','in_production','ready','shipped','delivered','cancelled','returned') | DEFAULT 'pending' |
| expected_delivery_date | date                                                                               | nullable                           |
| shipping_address       | text                                                                               | NOT NULL                           |
| notes                  | text                                                                               | nullable                           |
| created_at             | timestamp                                                                          |                                    |
| updated_at             | timestamp                                                                          |                                    |

> `user_id` registra qué empleado creó la orden.  
> `total_amount` siempre se calcula en el servidor sumando `order_items.subtotal`.

---

### 2.12 Tabla: `order_items`

| Columna              | Tipo          | Restricciones                     |
|----------------------|---------------|-----------------------------------|
| id                   | UUID          | PK                                |
| order_id             | UUID          | FK → orders.id, CASCADE DELETE    |
| product_id           | UUID          | FK → products.id                  |
| product_name         | varchar(200)  | Snapshot del nombre al momento    |
| quantity             | integer       | NOT NULL                          |
| unit_cost            | decimal(12,2) | Snapshot de cost_price            |
| unit_price           | decimal(12,2) | Snapshot de sale_price            |
| subtotal             | decimal(12,2) | unit_price × quantity             |
| line_profit          | decimal(12,2) | (unit_price − unit_cost) × qty    |
| customization_notes  | text          | nullable                          |
| reference_image_path | varchar(500)  | nullable (ruta en storage)        |
| created_at           | timestamp     | DEFAULT CURRENT_TIMESTAMP         |

> **Snapshot de precios:** `product_name`, `unit_cost`, `unit_price` se copian del producto al crear la orden. Cambios futuros en el producto NO afectan órdenes existentes.  
> Sin `updated_at` (los ítems no se editan post-creación).

---

### 2.13 Tabla: `payments`

| Columna        | Tipo                                          | Restricciones        |
|----------------|-----------------------------------------------|----------------------|
| id             | UUID                                          | PK                   |
| order_id       | UUID                                          | FK → orders.id       |
| payment_method | varchar(80)                                   | NOT NULL             |
| transaction_id | varchar(200)                                  | nullable             |
| amount         | decimal(12,2)                                 | NOT NULL             |
| status         | ENUM('pending','completed','failed','refunded')| DEFAULT 'pending'   |
| paid_at        | timestamp                                     | nullable             |
| created_at     | timestamp                                     |                      |
| updated_at     | timestamp                                     |                      |

**Métodos de pago válidos** (`Payment::METHODS`):
- `efectivo`
- `transferencia_bancaria`
- `tarjeta_debito`
- `tarjeta_credito`
- `cheque`
- `mercadopago`
- `otro`

---

### 2.14 Tabla: `order_returns`

| Columna       | Tipo                                              | Restricciones                         |
|---------------|---------------------------------------------------|---------------------------------------|
| id            | UUID                                              | PK                                    |
| order_id      | UUID                                              | FK → orders.id                        |
| order_item_id | UUID                                              | nullable, FK → order_items.id, SET NULL|
| return_type   | ENUM('full','partial')                            | DEFAULT 'full'                        |
| status        | ENUM('requested','approved','rejected','resolved')| DEFAULT 'requested'                  |
| reason        | text                                              | nullable                              |
| refund_amount | decimal(12,2)                                     | nullable                              |
| request_at    | timestamp                                         | DEFAULT CURRENT_TIMESTAMP             |
| resolved_at   | timestamp                                         | nullable                              |

> **Sin `timestamps` de Laravel** (`public $timestamps = false`). Solo `request_at` (gestionado por MySQL).  
> `order_item_id` es null en devoluciones completas (`full`) y requerido en parciales (`partial`).

---

### 2.15 Tabla: `order_handlers` (historial de gestión)

| Columna      | Tipo         | Restricciones                     |
|--------------|--------------|-----------------------------------|
| id           | UUID         | PK                                |
| order_id     | UUID         | FK → orders.id, CASCADE DELETE    |
| user_id      | UUID         | FK → users.id                     |
| handler_name | varchar(150) | Snapshot del nombre del empleado  |
| handler_role | varchar(100) | Snapshot del rol del empleado     |
| action_taken | text         | NOT NULL                          |
| notes        | text         | nullable                          |
| handled_at   | timestamp    | DEFAULT CURRENT_TIMESTAMP         |

> Log inmutable de auditoría. Cada cambio de estado y la creación de la orden genera un registro.

---

### 2.16 Tabla: `shipments`

| Columna        | Tipo                                                    | Restricciones                        |
|----------------|---------------------------------------------------------|--------------------------------------|
| id             | UUID                                                    | PK                                   |
| order_id       | UUID                                                    | FK → orders.id                       |
| tracking_number| varchar(100)                                            | nullable                             |
| courier_name   | varchar(100)                                            | nullable                             |
| handler_id     | UUID                                                    | nullable, FK → users.id, SET NULL    |
| status         | ENUM('preparing','shipped','in_transit','delivered','failed') | DEFAULT 'preparing'         |
| failure_reason | text                                                    | nullable                             |
| shipped_at     | timestamp                                               | nullable                             |
| delivered_at   | timestamp                                               | nullable                             |
| created_at     | timestamp                                               |                                      |
| updated_at     | timestamp                                               |                                      |

> El estado `failed` es terminal. Para reintentar se crea un nuevo envío.

---

### 2.17 Tabla: `production_orders`

| Columna            | Tipo                                              | Restricciones                           |
|--------------------|---------------------------------------------------|-----------------------------------------|
| id                 | UUID                                              | PK                                      |
| order_item_id      | UUID                                              | FK → order_items.id                     |
| assigned_worker_id | UUID                                              | nullable, FK → users.id, SET NULL       |
| status             | ENUM('queued','in_progress','completed','cancelled')| DEFAULT 'queued'                      |
| started_at         | timestamp                                         | nullable                                |
| completed_at       | timestamp                                         | nullable                                |
| internal_notes     | text                                              | nullable                                |
| created_at         | timestamp                                         |                                         |
| updated_at         | timestamp                                         |                                         |

---

### 2.18 Tablas auxiliares de Laravel

| Tabla                    | Propósito                              |
|--------------------------|----------------------------------------|
| `personal_access_tokens` | Sanctum API tokens                     |
| `cache`                  | Cache de Laravel                       |
| `jobs`                   | Cola de trabajos                       |
| `password_reset_tokens`  | Tokens de reset de contraseña          |
| `sessions`               | Sesiones web                           |

---

## 3. Modelos Eloquent

### 3.1 `User`
```
Traits: HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasUuids
```

| Relación           | Tipo        | Destino                     |
|--------------------|-------------|-----------------------------|
| `role()`           | BelongsTo   | Role                        |
| `customer()`       | HasOne      | Customer (perfil comercial) |

**Helpers:**
- `hasRole(string $roleSlug): bool`
- `hasPermission(string $permissionSlug): bool`

**Campos `$hidden`:** `password`, `remember_token`  
**`$casts`:** `is_active → boolean`, `password → hashed`

---

### 3.2 `Role`
```
Traits: HasFactory, HasUuids
```

| Relación         | Tipo           | Detalle                      |
|------------------|----------------|------------------------------|
| `permissions()`  | BelongsToMany  | Permission vía role_permission |
| `users()`        | HasMany        | User                         |

---

### 3.3 `Permission`
```
Traits: HasFactory, HasUuids
```

| Relación   | Tipo          | Detalle           |
|------------|---------------|-------------------|
| `roles()`  | BelongsToMany | Role vía role_permission |

---

### 3.4 `Category`
```
Traits: HasFactory, HasUuids
```

| Relación      | Tipo      | Detalle                             |
|---------------|-----------|-------------------------------------|
| `parent()`    | BelongsTo | Category (padre, auto-referencial)  |
| `children()`  | HasMany   | Category (subcategorías)            |
| `products()`  | HasMany   | Product                             |

---

### 3.5 `Product`
```
Traits: HasFactory, HasUuids
```

| Relación         | Tipo    | Detalle                              |
|------------------|---------|--------------------------------------|
| `category()`     | BelongsTo| Category                            |
| `images()`       | HasMany | ProductImage (tabla: products_images)|
| `primaryImage()` | HasOne  | ProductImage donde is_primary=true   |
| `inventory()`    | HasOne  | Inventory                            |

**Atributos calculados:**
- `primary_image_url` — URL pública de la imagen primaria (via `asset('storage/' . path)`)

---

### 3.6 `Customer`
```
Traits: HasFactory, HasUuids
```

| Relación  | Tipo      | Detalle                   |
|-----------|-----------|---------------------------|
| `user()`  | BelongsTo | User (cuenta opcional)    |
| `orders()`| HasMany   | Order                     |

**`$casts`:** `is_active → boolean`

---

### 3.7 `Order`
```
Traits: HasFactory, HasUuids
```

| Relación       | Tipo    | Detalle                           |
|----------------|---------|-----------------------------------|
| `customer()`   | BelongsTo| Customer                         |
| `user()`       | BelongsTo| User (quien creó la orden)       |
| `items()`      | HasMany | OrderItem                         |
| `payments()`   | HasMany | Payment                           |
| `returns()`    | HasMany | OrderReturn                       |
| `handlers()`   | HasMany | OrderHandler (ordenados por handled_at ASC)|
| `shipments()`  | HasMany | Shipment (ordenados por created_at DESC)|

**Atributos calculados:**
- `amount_paid` — suma de pagos `completed`
- `amount_pending` — `total_amount - amount_paid`
- `is_fully_paid` — booleano
- `allowed_transitions` — array de estados destino válidos

**Scopes:**
- `active()` — excluye cancelled, returned, delivered
- `byStatus(string|array $status)`
- `forCustomer(string $customerId)`

**Helpers:**
- `isActive(): bool`
- `canTransitionTo(string $status): bool`

**`$casts`:** `total_amount → decimal:2`, `expected_delivery_date → date`

---

### 3.8 `OrderItem`
```
Traits: HasFactory, HasUuids
```

| Relación              | Tipo      | Detalle                     |
|-----------------------|-----------|-----------------------------|
| `order()`             | BelongsTo | Order                       |
| `product()`           | BelongsTo | Product                     |
| `productionOrders()`  | HasMany   | ProductionOrder             |

---

### 3.9 `Inventory`
```
Traits: HasFactory, HasUuids
public $timestamps = false
```

| Relación    | Tipo      | Detalle  |
|-------------|-----------|----------|
| `product()` | BelongsTo | Product  |

**Atributos calculados:**
- `qty_sellable` — `max(0, qty_available - qty_reserved)`
- `is_low_stock` — `qty_sellable <= reorder_point`

**Scopes:**
- `lowStock()` — `(qty_available - qty_reserved) <= reorder_point`
- `forCategory(string $categoryId)`

---

### 3.10 `Payment`
```
Traits: HasFactory, HasUuids
```

**Constantes:**
```php
const METHODS  = ['efectivo','transferencia_bancaria','tarjeta_debito','tarjeta_credito','cheque','mercadopago','otro'];
const STATUSES = ['pending','completed','failed','refunded'];
```

| Relación  | Tipo      | Detalle |
|-----------|-----------|---------|
| `order()` | BelongsTo | Order   |

**Atributos calculados:**
- `is_refundable` — `status === 'completed'`
- `is_completable` — `status === 'pending'`

**Scopes:** `completed()`, `pending()`, `failed()`, `refunded()`, `forOrder(string $orderId)`

---

### 3.11 `Shipment`
```
Traits: HasFactory, HasUuids
```

**Constantes:**
```php
const STATUSES   = ['preparing','shipped','in_transit','delivered','failed'];
const TRANSITIONS = [
    'preparing'  => ['shipped', 'failed'],
    'shipped'    => ['in_transit', 'delivered', 'failed'],
    'in_transit' => ['delivered', 'failed'],
    'delivered'  => [],
    'failed'     => [],
];
```

| Relación    | Tipo      | Detalle                   |
|-------------|-----------|---------------------------|
| `order()`   | BelongsTo | Order                     |
| `handler()` | BelongsTo | User (gestor del envío)   |

**Atributos calculados:**
- `allowed_transitions` — array según estado actual
- `is_active` — not in [delivered, failed]
- `is_delivered` — `status === 'delivered'`

**Scopes:** `forOrder()`, `active()`, `byStatus()`, `withCourier()`

---

### 3.12 `ProductionOrder`
```
Traits: HasFactory, HasUuids
tabla: production_orders
```

**Constantes:**
```php
const STATUSES   = ['queued','in_progress','completed','cancelled'];
const TRANSITIONS = [
    'queued'      => ['in_progress', 'cancelled'],
    'in_progress' => ['completed', 'cancelled'],
    'completed'   => [],
    'cancelled'   => [],
];
```

| Relación      | Tipo      | Detalle               |
|---------------|-----------|-----------------------|
| `orderItem()` | BelongsTo | OrderItem             |
| `worker()`    | BelongsTo | User (trabajador asignado)|

**Atributos calculados:**
- `allowed_transitions`
- `is_active` — not in [completed, cancelled]
- `can_start`, `can_complete`, `can_cancel` — booleanos de acción

**Scopes:** `byStatus()`, `active()`, `forOrder()`, `forWorker()`, `unassigned()`

---

### 3.13 `OrderReturn`
```
Traits: HasFactory, HasUuids
tabla: order_returns
public $timestamps = false
```

**Constantes:**
```php
const TYPES    = ['full','partial'];
const STATUSES = ['requested','approved','rejected','resolved'];
const BLOCKING_STATUSES = ['requested','approved'];
const TRANSITIONS = [
    'requested' => ['approved','rejected'],
    'approved'  => ['resolved'],
    'rejected'  => [],
    'resolved'  => [],
];
```

| Relación      | Tipo      | Detalle              |
|---------------|-----------|----------------------|
| `order()`     | BelongsTo | Order                |
| `orderItem()` | BelongsTo | OrderItem (nullable) |

**Atributos calculados:**
- `allowed_transitions`
- `can_be_approved`, `can_be_rejected`, `can_be_resolved`

**Scopes:** `forOrder()`, `active()` (en BLOCKING_STATUSES), `byStatus()`

---

## 4. Sistema de Autenticación y RBAC

### 4.1 Flujo de autenticación

```
POST /api/login → { access_token, token_type: "Bearer", user }
                        ↓
          localStorage('access_token')
                        ↓
GET /api/me → { data: { id, name, email, role: { permissions: [...] } } }
```

### 4.2 Permisos definidos (seedeados)

| Slug                   | Módulo          | Descripción                     |
|------------------------|-----------------|---------------------------------|
| `modulo-pedidos`       | Operaciones     | Acceso al módulo de pedidos     |
| `modulo-categorias`    | Catálogo        | Acceso al módulo de categorías  |
| `modulo-productos`     | Catálogo        | Acceso al módulo de productos   |
| `modulo-usuarios`      | Administración  | Acceso al módulo de usuarios    |
| `modulo-roles`         | Administración  | Acceso al módulo de roles       |
| `modulo-configuracion` | Administración  | Acceso al módulo de configuración|

> ⚠️ **`modulo-clientes` NO está seedeado.** Ver sección 11 (Gaps).

### 4.3 Roles seedeados

| Slug          | Nombre               | Permisos                                                          |
|---------------|----------------------|-------------------------------------------------------------------|
| `super-admin` | Super Administrador  | Todos los permisos                                                |
| `vendedor`    | Gestor de Ventas     | modulo-pedidos, modulo-categorias, modulo-productos               |

### 4.4 Verificación de permisos (backend)

```php
// En User model:
$user->hasPermission('modulo-clientes'); // consulta role → permissions via DB

// En router frontend (Vue):
authStore.hasPermission('modulo-clientes') // revisa user.role.permissions[].slug
```

---

## 5. API REST — Referencia Completa

### Convenciones

- Todas las respuestas exitosas: `200 OK` o `201 Created`
- Errores de validación: `422 Unprocessable Entity` con `{ message, errors }`
- No autorizado: `401 Unauthorized`
- No encontrado: `404 Not Found`
- Paginación: `{ data: [...], current_page, last_page, per_page, total, from, to }`

---

### 5.1 Autenticación

#### `POST /api/login` — Pública
```json
Body: { "email": "string", "password": "string" }
Response 200: { "access_token": "string", "token_type": "Bearer", "user": { UserResource } }
Response 422: { "message": "Credenciales incorrectas." }
```

#### `POST /api/logout` — Protegida
```json
Response 200: { "message": "Sesión cerrada correctamente." }
```

#### `GET /api/me` — Protegida
```json
Response 200: { "data": { UserResource con role y permissions cargados } }
```

---

### 5.2 Usuarios (`/api/users`)

#### `GET /api/users`
```
Filtros: ?search=nombre_o_email  ?page=1
Paginación: 15 por página
Response: UserResource collection
```

#### `POST /api/users`
```json
Body: {
  "role_id": "uuid (required)",
  "name": "string max:150 (required)",
  "email": "email unique:users (required)",
  "password": "string min:8 (required)",
  "phone": "string max:30 (nullable)",
  "is_active": "boolean (nullable, default true)",
  "customer_type": "individual|business (nullable)",
  "business_name": "string max:150 (required_if:customer_type,business)",
  "tax_id": "string max:50 (nullable)"
}
Response 201: { "message": "...", "user": UserResource }
```

> `customer_type` y `business_name` se usan solo si el usuario también es cliente. Ver UserController para lógica de creación de Customer asociado.

#### `PUT|PATCH /api/users/{user}`
```json
Body: mismos campos que POST, excepto:
  - password: nullable (omitir para no cambiar)
  - role_id: nullable
  - email: unique ignorando propio id
```

#### `DELETE /api/users/{user}` — Soft delete (sets deleted_at)
```json
Response 200: { "message": "Usuario desactivado." }
```

#### `PATCH /api/users/{user}/restore`
```json
Response 200: { "message": "Usuario restaurado." }
```

---

### 5.3 Roles (`/api/roles`)

#### `GET /api/roles`
```
Sin paginación — devuelve todos los roles con sus permisos cargados.
Response: RoleResource collection
```

#### `POST /api/roles`
```json
Body: { "name": "string", "slug": "string", "description": "string|null", "permissions": ["uuid",...] }
```

#### `PUT|PATCH /api/roles/{role}` — Actualiza + sincroniza permisos

#### `DELETE /api/roles/{role}` — Desactivación lógica (`is_active = false`)

#### `PATCH /api/roles/{role}/restore` — Reactiva (`is_active = true`)

#### `GET /api/permissions`
```
Devuelve todos los permisos activos. Sin paginación.
```

---

### 5.4 Categorías (`/api/categories`)

#### `GET /api/categories`
```
Filtros: ?search=nombre  ?active=true|false  ?parent_id=uuid|null
Incluye: relación parent y children
```

#### `POST /api/categories`
```json
Body: {
  "name": "string max:100 (required)",
  "parent_id": "uuid exists:categories (nullable)",
  "description": "string (nullable)",
  "image_url": "string (nullable)",
  "is_active": "boolean (nullable)"
}
```

#### `DELETE /api/categories/{category}` — Desactivación lógica

#### `PATCH /api/categories/{category}/restore`

---

### 5.5 Productos (`/api/products`)

#### `GET /api/products` — Protegida
```
Filtros: ?search=texto  ?category_id=uuid  ?active=true|false  ?featured=true|false
         ?nopaginate=true  (devuelve todos sin paginar)
Paginación: 15 por página (si pagina)
Incluye: category, primaryImage, inventory
```

#### `GET /api/public/products` — Pública
```
Mismos filtros. Sin autenticación. Para landing/catálogo público.
```

#### `POST /api/products`
```json
Body: {
  "category_id": "uuid exists:categories (required)",
  "sku": "string max:50 unique (required)",
  "name": "string max:255 (required)",
  "description": "string (nullable)",
  "base_price": "numeric min:0 (required)",
  "cost_price": "numeric min:0 (required)",
  "sale_price": "numeric min:0 (required)",
  "production_lead_time_days": "integer min:0 (required)",
  "attributes": "array (nullable)",
  "is_active": "boolean",
  "is_featured": "boolean"
}
```
> Al crear un producto, `InventoryService::initForProduct()` crea su registro de inventario automáticamente con qty=0.

#### `GET /api/products/{product}` — incluye imágenes, inventario, categoría

#### `DELETE /api/products/{product}` — Desactivación lógica

#### `PATCH /api/products/{product}/restore`

---

### 5.6 Imágenes de Producto (`/api/products/{product}/images`)

#### `GET /api/products/{product}/images`
```
Response: array de ProductImageResource, ordenado por sort_order
```

#### `POST /api/products/{product}/images`
```
Content-Type: multipart/form-data
Body: { "image": file (jpg|jpeg|png|webp|gif), "is_primary": boolean }
Almacena en: storage/app/public/products/{product_id}/
Response 201: ProductImageResource
```

> Al marcar una imagen como primary, la anterior primary se desmarca automáticamente.

#### `PUT /api/products/{product}/images/{image}/primary`
```
Sin body. Marca la imagen como primaria.
```

#### `DELETE /api/products/{product}/images/{image}`
```
Elimina físicamente el archivo y el registro.
```

---

### 5.7 Configuración de Empresa

#### `GET /api/settings` — Protegida  
#### `GET /api/public/settings` — Pública

#### `POST /api/settings` — Actualiza (upsert)
```json
Body: cualquier campo de company_settings (todos nullable)
Acepta: multipart/form-data (para subir hero_image_path o about_image_path)
```

---

### 5.8 Clientes (`/api/customers`)

#### `GET /api/customers`
```
Filtros: ?search=texto  ?type=individual|business  ?active=true|false  ?page=1
Paginación: 15 por página
Incluye (automático): orders_count, active_orders_count, total_spent (de órdenes 'delivered')
```

#### `POST /api/customers`
```json
Body: {
  "customer_type": "individual|business (required)",
  "name": "string max:150 (required)",
  "email": "email unique:customers (required)",
  "business_name": "string max:150 (required_if:customer_type,business)",
  "tax_id": "string max:50 (nullable)",
  "phone": "string max:30 (nullable)",
  "user_id": "uuid exists:users (nullable)",
  "is_active": "boolean (nullable)"
}
Response 201: { "message": "...", "customer": CustomerResource }
```

#### `GET /api/customers/{customer}`
```
Incluye: user (id,name,email), orders_count, active_orders_count, total_spent,
         recentOrders (últimas 10: id,status,total_amount,created_at)
```

#### `PUT|PATCH /api/customers/{customer}`
```json
Body: mismos campos que POST (todos opcionales con 'sometimes')
      email: unique ignorando propio id
```

#### `DELETE /api/customers/{customer}` — Desactivación lógica
```
⚠️ BLOQUEADO si el cliente tiene órdenes en estados activos:
   ['pending','confirmed','in_production','ready','shipped']
Response 422: { "message": "No se puede desactivar un cliente con órdenes activas..." }
```

#### `PATCH /api/customers/{customer}/restore`
```json
Response 200: { "message": "...", "customer": CustomerResource }
Response 422: { "message": "El cliente ya está activo." }
```

---

### 5.9 Inventario (`/api/inventory`)

#### `GET /api/inventory`
```
Filtros: ?search=sku_o_nombre  ?category_id=uuid  ?active=true|false  ?low_stock=true
         ?page=1
Paginación: 20 por página
Incluye: product.category
Ordenado por: qty_sellable ASC (primero los más bajos)
```

#### `GET /api/inventory/{product}` — por product_id o UUID del producto
```
Incluye: product.category
```

#### `POST /api/inventory/{product}/adjust`
```json
Body: {
  "qty_available": "integer min:0 (required)",
  "reorder_point": "integer min:0 (nullable)",
  "reason": "string min:5 max:500 (required)"
}
Response 200: { "message": "...", "inventory": InventoryResource, "warning"?: "..." }
```

> `warning` aparece si `qty_available < qty_reserved` (stock en riesgo).  
> El ajuste usa `lockForUpdate()` para evitar race conditions.

---

### 5.10 Órdenes (`/api/orders`)

#### `GET /api/orders`
```
Filtros: ?status=pending  ?status[]=pending&status[]=confirmed
         ?customer_id=uuid  ?user_id=uuid
         ?from=YYYY-MM-DD  ?to=YYYY-MM-DD
         ?search=texto (busca en order.id o customer.name/business_name)
         ?page=1
Paginación: 20 por página
Incluye: customer, user, items (campos básicos), items_count, amount_paid
```

#### `POST /api/orders`
```
Content-Type: multipart/form-data (para imágenes de referencia por ítem)

Body: {
  "customer_id": "uuid exists:customers (required)",
  "shipping_address": "string max:1000 (required)",
  "notes": "string max:2000 (nullable)",
  "expected_delivery_date": "date after_or_equal:today (nullable)",
  "items": [
    {
      "product_id": "uuid exists:products (required)",
      "quantity": "integer min:1 max:99999 (required)",
      "customization_notes": "string max:1000 (nullable)",
      "reference_image": "file image max:5MB (nullable)"
    }
  ]
}

Validaciones adicionales:
  - El cliente debe estar activo
  - No puede haber dos ítems con el mismo product_id

Proceso interno (OrderService::create):
  1. Carga todos los productos en una query
  2. Valida que existan y estén activos
  3. Calcula precios (unit_price=sale_price, unit_cost=cost_price) en servidor
  4. Calcula total_amount = suma de subtotales
  5. Estima expected_delivery_date si no se proporcionó (max lead_time_days + hoy)
  6. Crea Order + OrderItems en transacción atómica
  7. Registra en order_handlers

Response 201: { "message": "...", "order": OrderResource }
```

#### `GET /api/orders/{order}`
```
Incluye: customer, user, items, payments, shipments, handlers
```

**OrderResource expone:**
- Datos básicos de la orden
- `amount_paid` — suma pagos completados
- `amount_pending` — total - amount_paid
- `is_fully_paid`
- `allowed_transitions` — array de estados posibles

#### `PATCH /api/orders/{order}/status`
```json
Body: {
  "status": "confirmed|in_production|ready|shipped|delivered|cancelled|returned (required)",
  "notes": "string max:1000 (nullable)"
}

Efectos secundarios automáticos:
  - pending → confirmed : reserva stock (InsufficientStockException si falta)
  - * → cancelled       : libera stock reservado (si estado tenía stock reservado)

Response 422 si transición inválida: {
  "message": "...",
  "allowed_transitions": [...]
}
Response 422 si stock insuficiente: {
  "message": "...",
  "requested": int,
  "available": int,
  "product": string
}
```

#### `DELETE /api/orders/{order}`
```
Solo cancela órdenes en estado 'pending'.
Para otros estados usar PATCH /status con status=cancelled.
Response 422: { "message": "Solo se pueden cancelar directamente órdenes en estado 'pending'..." }
```

---

### 5.11 Órdenes de Producción

#### `GET /api/production-orders`
```
Lista global de todas las órdenes de producción.
Filtros: ?status=queued|in_progress|completed|cancelled  ?page=1
Incluye: orderItem, worker
```

#### `GET /api/orders/{order}/production-orders`
#### `POST /api/orders/{order}/production-orders`
```json
Body: {
  "order_item_id": "uuid exists:order_items (nullable)",
  "assigned_worker_id": "uuid exists:users (nullable)",
  "internal_notes": "string max:1000 (nullable)"
}
```

#### `GET /api/orders/{order}/production-orders/{prodOrder}`
#### `PATCH /api/orders/{order}/production-orders/{prodOrder}/start`
```
queued → in_progress. Sin body.
Efecto: InventoryService::moveToProduction() (qty_reserved→qty_in_production)
```

#### `PATCH /api/orders/{order}/production-orders/{prodOrder}/complete`
```
in_progress → completed. Sin body.
Efecto: InventoryService::completeProduction() (qty_in_production→qty_available)
```

#### `PATCH /api/orders/{order}/production-orders/{prodOrder}/cancel`
```
queued|in_progress → cancelled. Sin body.
Efecto: si estaba in_progress → InventoryService::cancelProduction() (revierte a reserved)
```

#### `PATCH /api/orders/{order}/production-orders/{prodOrder}/assign`
```json
Body: { "assigned_worker_id": "uuid|null" }
```

---

### 5.12 Envíos (`/api/shipments`)

#### `GET /api/shipments`
```
Lista global de envíos.
Filtros: ?status=preparing|shipped|in_transit|delivered|failed  ?page=1
```

#### `POST /api/orders/{order}/shipments`
```json
Body: {
  "tracking_number": "string max:100 (nullable)",
  "courier_name": "string max:100 (nullable)",
  "handler_id": "uuid exists:users (nullable)",
  "notes": "string max:500 (nullable)"
}
```

#### `PATCH /api/orders/{order}/shipments/{shipment}/dispatch`
```
preparing → shipped. Registra shipped_at = now().
```

#### `PATCH /api/orders/{order}/shipments/{shipment}/in-transit`
```
shipped → in_transit.
```

#### `PATCH /api/orders/{order}/shipments/{shipment}/deliver`
```
shipped|in_transit → delivered. Registra delivered_at = now().
```

#### `PATCH /api/orders/{order}/shipments/{shipment}/fail`
```json
Body: { "failure_reason": "string (requerido al llamar este endpoint)" }
preparing|shipped|in_transit → failed.
Estado failed es terminal: crear nuevo Shipment para reintento.
```

---

### 5.13 Pagos (`/api/payments`)

#### `GET /api/payments`
```
Lista global de pagos.
Filtros: ?status=pending|completed|failed|refunded  ?order_id=uuid  ?page=1
```

#### `POST /api/orders/{order}/payments`
```json
Body: {
  "payment_method": "efectivo|transferencia_bancaria|tarjeta_debito|tarjeta_credito|cheque|mercadopago|otro (required)",
  "amount": "numeric min:0.01 (required)",
  "transaction_id": "string max:255 (nullable)",
  "mark_completed": "boolean (optional, true = completa el pago inmediatamente)",
  "notes": "string max:500 (nullable)"
}
```

#### `PATCH /api/orders/{order}/payments/{payment}/complete`
```
pending → completed. Registra paid_at = now().
```

#### `PATCH /api/orders/{order}/payments/{payment}/fail`
```
pending → failed.
```

#### `PATCH /api/orders/{order}/payments/{payment}/refund`
```
completed → refunded.
```

---

### 5.14 Devoluciones (`/api/returns`)

#### `GET /api/returns`
```
Lista global de devoluciones.
Filtros: ?status=requested|approved|rejected|resolved  ?page=1
```

#### `POST /api/orders/{order}/returns`
```json
Body: {
  "return_type": "full|partial (required)",
  "order_item_id": "uuid exists:order_items (required si partial, debe estar vacío si full)",
  "reason": "string max:1000 (nullable)",
  "refund_amount": "numeric min:0.01 (nullable)"
}
Validaciones adicionales:
  - partial + sin order_item_id → error
  - full + con order_item_id → error
  - No puede haber devolución activa (requested|approved) para la misma orden
```

#### `GET /api/orders/{order}/returns/{return}`

#### `PATCH /api/orders/{order}/returns/{return}/approve`
```
requested → approved.
```

#### `PATCH /api/orders/{order}/returns/{return}/reject`
```json
Body opcional: { "notes": "string max:500" }
requested → rejected.
```

#### `PATCH /api/orders/{order}/returns/{return}/resolve`
```json
Body: { "refund_amount": "numeric min:0.01 (nullable)", "notes": "string max:500 (nullable)" }
approved → resolved. Registra resolved_at = now().
```

---

## 6. Capa de Servicios

### 6.1 `OrderService`

Inyecta `InventoryService`. Maneja creación y transiciones de estado de órdenes.

**`create(array $data, User $actor): Order`**
- Transacción atómica
- Precios siempre del servidor (sale_price, cost_price del producto)
- Snapshot de nombre y precios en order_items
- Calcula expected_delivery_date si no viene en request (max lead_time_days)
- Registra en order_handlers

**`transition(Order $order, string $newStatus, User $actor, ?string $notes): Order`**
- Valida transición contra `TRANSITIONS` map — lanza `\LogicException` si inválida
- Efectos secundarios:
  - `→ confirmed`: `reserveStock()` → `InventoryService::reserve()`
  - `→ cancelled`: `releaseStockIfNeeded()` → `InventoryService::release()` (solo si el estado tenía stock reservado)
- Registra en order_handlers

**Estados con stock reservado** (al cancelar desde estos, se libera):
`confirmed`, `in_production`, `ready`, `shipped`

### 6.2 `InventoryService`

Todas las operaciones usan `lockForUpdate()` para evitar race conditions en concurrencia.

| Método                                    | Efecto en inventario                          |
|-------------------------------------------|-----------------------------------------------|
| `initForProduct(Product $p)`              | Crea registro con qty=0                       |
| `reserve(productId, qty, name)`           | `qty_reserved += qty` (lanza exception si falta stock) |
| `release(productId, qty)`                 | `qty_reserved -= qty`                         |
| `adjust(Inventory, newQty, reorderPoint, reason)` | Set directo + log en `Log::info('inventory.adjusted')` |
| `moveToProduction(productId, qty)`        | `qty_reserved -= qty`, `qty_in_production += qty` |
| `cancelProduction(productId, qty)`        | `qty_in_production -= qty`, `qty_reserved += qty` |
| `completeProduction(productId, qty)`      | `qty_in_production -= qty`, `qty_available += qty` |

**`InsufficientStockException`:** lanzada por `reserve()` cuando `qty_sellable < qty_solicitada`. Expone `->productName`, `->requested`, `->available`.

---

## 7. Máquinas de Estado

### 7.1 Estado de Orden

```
          ┌──────────────────────────────────────────────────────┐
          │                  cancelled (terminal)                │
          │                       ↑                              │
pending ──┼→ confirmed ──→ in_production ──→ ready ──→ shipped   │
          │      ↑                ↑              ↑        │      │
          │      └────────────────┴──────────────┘        ↓      │
          │                                           delivered  │
          │                                               │      │
          └───────────────────────────────────────────────┘      │
                                                          ↓      │
                                                       returned  │
                                                      (terminal) │
          └──────────────────────────────────────────────────────┘
```

| Desde          | Puede ir a                        |
|----------------|-----------------------------------|
| pending        | confirmed, cancelled              |
| confirmed      | in_production, cancelled          |
| in_production  | ready, cancelled                  |
| ready          | shipped, cancelled                |
| shipped        | delivered                         |
| delivered      | returned                          |
| cancelled      | — (terminal)                      |
| returned       | — (terminal)                      |

### 7.2 Estado de Envío (`Shipment`)

| Desde      | Puede ir a                          |
|------------|-------------------------------------|
| preparing  | shipped, failed                     |
| shipped    | in_transit, delivered, failed       |
| in_transit | delivered, failed                   |
| delivered  | — (terminal)                        |
| failed     | — (terminal, crear nuevo shipment)  |

### 7.3 Estado de Orden de Producción (`ProductionOrder`)

| Desde       | Puede ir a                |
|-------------|---------------------------|
| queued      | in_progress, cancelled    |
| in_progress | completed, cancelled      |
| completed   | — (terminal)              |
| cancelled   | — (terminal)              |

### 7.4 Estado de Devolución (`OrderReturn`)

| Desde     | Puede ir a         |
|-----------|--------------------|
| requested | approved, rejected |
| approved  | resolved           |
| rejected  | — (terminal)       |
| resolved  | — (terminal)       |

### 7.5 Estado de Pago (`Payment`)

| Desde     | Puede ir a |
|-----------|------------|
| pending   | completed, failed |
| completed | refunded   |
| failed    | — (terminal) |
| refunded  | — (terminal) |

---

## 8. Reglas de Negocio Críticas

1. **Precios calculados en servidor:** `unit_price = sale_price`, `unit_cost = cost_price`. El frontend nunca envía precios — solo `product_id` y `quantity`.

2. **Snapshot de precios en orden:** `product_name`, `unit_price`, `unit_cost` se copian al crear la orden. Cambios posteriores en el producto no afectan la orden.

3. **Reserva de stock:** El stock **no** se reserva al crear la orden (`pending`). Se reserva al confirmarla (`pending → confirmed`). Si falta stock → `422` con detalles del producto y cantidades.

4. **Cancelación libera stock:** Solo si la orden estaba en estados con stock reservado (`confirmed`, `in_production`, `ready`, `shipped`).

5. **Producción y stock:**
   - `queued → in_progress`: `qty_reserved → qty_in_production`
   - `in_progress → completed`: `qty_in_production → qty_available`
   - `in_progress → cancelled`: `qty_in_production → qty_reserved` (revierte)

6. **Cliente activo requerido:** No se puede crear una orden para un cliente con `is_active = false`.

7. **No duplicar productos en orden:** Dos líneas del mismo `product_id` en una orden generan `422`. Incrementar la cantidad en la misma línea.

8. **Desactivar cliente bloqueado:** Si el cliente tiene órdenes en `['pending','confirmed','in_production','ready','shipped']`, la desactivación retorna `422`.

9. **Empresa requiere razón social:** Si `customer_type = 'business'`, `business_name` es obligatorio en creación.

10. **`DELETE /orders/{order}` solo para pending:** Para cancelar desde otros estados usar `PATCH /orders/{order}/status` con `status = cancelled`.

11. **Devoluciones exclusivas:** No puede haber más de una devolución activa (`requested` o `approved`) por orden al mismo tiempo.

12. **Historial de orden es inmutable:** `order_handlers` solo tiene inserts, nunca updates ni deletes.

13. **Inventario con lockForUpdate:** Todas las operaciones de stock usan `lockForUpdate()` dentro de una transacción para garantizar consistencia bajo concurrencia.

14. **Fecha de entrega estimada:** Si no se proporciona `expected_delivery_date`, se calcula como `hoy + max(production_lead_time_days de los productos)`. Mínimo 1 día.

---

## 9. Almacenamiento de Archivos

| Propósito                     | Ruta en storage                    | Endpoint                         |
|-------------------------------|------------------------------------|----------------------------------|
| Imágenes de producto          | `products/{product_id}/{filename}` | POST `/products/{id}/images`     |
| Imágenes de referencia (órdenes) | `orders/references/{filename}`  | POST `/orders` (multipart)       |
| Logo/imagen hero empresa      | Configurable en `company_settings` | POST `/settings`                 |

- **Disco:** `public` (accesible via `/storage/...`)
- **URL pública:** `asset('storage/' . $path)`
- **Límite de imagen de referencia:** 5 MB, formatos: jpg, jpeg, png, webp
- **Simbólico requerido:** `php artisan storage:link`

---

## 10. Seeders y Datos Iniciales

### Orden de ejecución (`DatabaseSeeder`)
1. `RoleAndPermissionSeeder` — permisos + roles (super-admin, vendedor)
2. `UserSeeder` — usuario administrador
3. `PlasticCompanySeeder` — empresa, categorías, productos con imágenes
4. `InventorySeeder` — stock inicial para todos los productos

### Datos del UserSeeder
```
Email:    admin@soluplast.com  (o similar)
Password: password (o definido en seeder)
Rol:      super-admin
```

### Datos del PlasticCompanySeeder
**Categorías creadas:**
- Envases Industriales (3 productos)
- Menaje y Hogar (3 productos)
- Embalajes Flexibles (2 productos)
- Botellas y Tapas (4 productos)

**Total:** 12 productos de muestra con precios, SKU aleatorio, atributos JSON y una imagen primaria (URL de Unsplash).

**Empresa de ejemplo:** Plastix Industrial S.A. (Argentina) — datos ficticios para demo.

---

## 11. Gaps y Pendientes

### 11.1 Permiso `modulo-clientes` no seedeado

El módulo de clientes está completamente implementado en backend y frontend, pero el permiso **no está en `RoleAndPermissionSeeder`**.

**Acción requerida:** Agregar al seeder y ejecutar, O insertar manualmente:

```sql
-- Insertar permiso
INSERT INTO permissions (id, name, slug, module, is_active, created_at, updated_at)
VALUES (UUID(), 'Acceso al Módulo Clientes', 'modulo-clientes', 'Operaciones', 1, NOW(), NOW());

-- Asignar al rol super-admin (ajustar UUID del rol)
INSERT INTO role_permission (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW()
FROM roles r, permissions p
WHERE r.slug = 'super-admin' AND p.slug = 'modulo-clientes';
```

O agregar al `RoleAndPermissionSeeder`:
```php
['name' => 'Acceso al Módulo Clientes', 'slug' => 'modulo-clientes', 'module' => 'Operaciones'],
```

### 11.2 Sin autorización granular en controladores

Actualmente `authorize(): bool { return true; }` en todos los FormRequests. No hay validación de permisos a nivel de controlador/request — solo a nivel de ruta frontend. Para producción, agregar `$this->user()->hasPermission(...)` en el método `authorize()`.

### 11.3 `order_handlers` sin modelo expuesto en API

La tabla `order_handlers` existe y se llena automáticamente, pero no hay endpoint `GET /api/orders/{order}/handlers` documentado explícitamente. La data sí se incluye en `GET /api/orders/{order}/show` via relación `handlers`.

### 11.4 `UserController` crea Customer asociado

Al crear un usuario con `customer_type` no nulo, se crea también un perfil `Customer` vinculado. Esta lógica hace que `UsuariosView` filtre los usuarios que tienen `customer` (los oculta del listado de staff). Documentar este comportamiento para evitar confusión.

### 11.5 Falta endpoint de resumen/dashboard

No existe un endpoint `GET /api/dashboard` con KPIs agregados (ventas del mes, órdenes activas, stock bajo, etc.). Es necesario para el módulo Dashboard del frontend.

### 11.6 Sin validación de producto activo al confirmar orden

El `OrderService::reserveStock()` no verifica si el producto sigue activo al momento de confirmar (solo se valida al crear). Si un producto se desactiva entre la creación y la confirmación, el stock se reserva igual.

---

*Documento generado automáticamente desde el código fuente. Actualizar ante cambios en modelos, migraciones o controladores.*
