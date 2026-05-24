# Plan de Desarrollo Backend — Ecommerce Codesoft
**Stack:** Laravel 12 · PHP 8.2 · MySQL · Sanctum  
**Fecha de creación:** 2026-05-24  
**Autor del plan:** Senior Backend Developer

---

## Estado General del Proyecto

| # | Módulo | Estado |
|---|--------|--------|
| 1 | Auth & Sesión | ✅ Completo |
| 2 | RBAC (Roles & Permisos) | ✅ Completo |
| 3 | Categorías | ✅ Completo |
| 4 | Catálogo de Productos + Imágenes | ✅ Completo |
| 5 | Configuración de Empresa | ✅ Completo |
| 6 | Clientes (Customers) | 🔶 Modelo existe · Sin endpoints |
| 7 | Inventario | 🔶 Modelo existe · Sin endpoints |
| 8 | Órdenes & Items | 🔶 Modelo existe · Sin endpoints |
| 9 | Pagos | 🔶 Modelo existe · Sin endpoints |
| 10 | Devoluciones | 🔶 Modelo existe · Sin endpoints |
| 11 | Logística & Envíos | 🔶 Modelo existe · Sin endpoints |
| 12 | Producción | 🔶 Modelo existe · Sin endpoints |
| 13 | Dashboard & Reportes | ❌ Pendiente |

---

## Módulos Completados

### Módulo 1 — Auth & Sesión ✅
**Tabla:** `users` (vía Sanctum)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/login` | Login con email + password, retorna token |
| POST | `/api/logout` | Revoca el token actual |
| GET | `/api/me` | Devuelve el usuario autenticado con su rol |

**Archivos:** `AuthController.php`

---

### Módulo 2 — RBAC (Roles & Permisos) ✅
**Tablas:** `roles`, `permissions`, `role_permission`

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET/POST | `/api/roles` | Listar / Crear roles |
| GET/PUT/DELETE | `/api/roles/{id}` | Ver / Editar / Eliminar rol |
| GET | `/api/permissions` | Listar todos los permisos |
| GET/POST | `/api/users` | Listar / Crear usuarios |
| GET/PUT/DELETE | `/api/users/{id}` | Ver / Editar / Eliminar usuario |

**Archivos:** `RoleController.php`, `PermissionController.php`, `UserController.php`

---

### Módulo 3 — Categorías ✅
**Tabla:** `categories` (soporta subcategorías vía `parent_id`)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/public/categories` | Listar categorías activas (público) |
| GET/POST | `/api/categories` | Listar / Crear categorías (admin) |
| GET/PUT/DELETE | `/api/categories/{id}` | Ver / Editar / Eliminar categoría |

**Archivos:** `CategoryController.php`

---

### Módulo 4 — Catálogo de Productos & Imágenes ✅
**Tablas:** `products`, `products_images`

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/public/products` | Catálogo público con filtros |
| GET | `/api/public/products/{id}` | Detalle de producto público |
| GET/POST | `/api/products` | Listar / Crear productos (admin) |
| GET/PUT/DELETE | `/api/products/{id}` | Ver / Editar / Eliminar producto |
| GET/POST | `/api/products/{id}/images` | Listar / Subir imágenes |
| PUT | `/api/products/{id}/images/{img}/primary` | Marcar imagen principal |
| DELETE | `/api/products/{id}/images/{img}` | Eliminar imagen |

**Archivos:** `ProductController.php`, `ProductImageController.php`, `StoreProductRequest.php`, `UpdateProductRequest.php`

---

### Módulo 5 — Configuración de Empresa ✅
**Tabla:** `company_settings`

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/public/settings` | Configuración pública (nombre, logo, etc.) |
| GET | `/api/settings` | Configuración completa (admin) |
| POST | `/api/settings` | Actualizar configuración |

**Archivos:** `CompanySettingController.php`

---

## Módulos Pendientes — Roadmap Priorizado

---

### Módulo 6 — Clientes (Customers) 🔶
**Tabla:** `customers`  
**Prioridad:** ALTA — bloquea Módulo 8 (Órdenes)  
**Descripción:** Gestión de clientes B2B y B2C. Un cliente puede tener o no una cuenta de usuario (`user_id` opcional).

#### Endpoints a implementar

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/api/customers` | ✅ | Listar clientes (con filtros: tipo, activo, búsqueda) |
| POST | `/api/customers` | ✅ | Crear cliente (individual o business) |
| GET | `/api/customers/{id}` | ✅ | Ver detalle + historial de órdenes |
| PUT | `/api/customers/{id}` | ✅ | Actualizar datos del cliente |
| DELETE | `/api/customers/{id}` | ✅ | Soft-delete (marcar `is_active = 0`) |

#### Lógica de negocio
- Validar unicidad de `email` al crear/editar.
- Si `customer_type = 'business'`, `business_name` y `tax_id` son obligatorios.
- Bloquear eliminación definitiva si el cliente tiene órdenes asociadas.
- El campo `user_id` es opcional; asignar solo si el cliente tiene cuenta de acceso.

#### Archivos a crear
```
app/Http/Controllers/Api/CustomerController.php
app/Http/Requests/Customer/StoreCustomerRequest.php
app/Http/Requests/Customer/UpdateCustomerRequest.php
app/Http/Resources/CustomerResource.php
```

---

### Módulo 7 — Inventario 🔶
**Tabla:** `inventory`  
**Prioridad:** ALTA — el stock se ajusta automáticamente al crear/cancelar órdenes  
**Descripción:** Control de stock por producto. Cada producto tiene exactamente un registro en `inventory`.

#### Endpoints a implementar

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/api/inventory` | ✅ | Listar inventario (paginado, filtro por bajo stock) |
| GET | `/api/inventory/{product_id}` | ✅ | Ver stock de un producto específico |
| PUT | `/api/inventory/{product_id}` | ✅ | Ajuste manual de stock (con motivo) |
| GET | `/api/inventory/low-stock` | ✅ | Productos en o por debajo del `reorder_point` |

#### Lógica de negocio
- `qty_available` = stock físico disponible para venta.
- `qty_reserved` = reservado por órdenes en estado `pending` / `confirmed` / `in_production`.
- `qty_in_production` = ligado a `production_orders` activas.
- **Al crear una orden:** `qty_reserved += quantity`.
- **Al cancelar una orden:** `qty_reserved -= quantity`.
- **Al completar producción:** `qty_in_production -= qty`, `qty_available += qty`.
- Nunca permitir que `qty_available - qty_reserved < 0`.

#### Archivos a crear
```
app/Http/Controllers/Api/InventoryController.php
app/Http/Requests/Inventory/UpdateInventoryRequest.php
app/Http/Resources/InventoryResource.php
app/Services/InventoryService.php   ← lógica de ajuste desacoplada
```

---

### Módulo 8 — Órdenes & Items 🔶
**Tablas:** `orders`, `order_items`  
**Prioridad:** CRÍTICA — núcleo del sistema  
**Descripción:** Ciclo de vida completo de una orden, desde su creación hasta entrega o cancelación.

#### Endpoints a implementar

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/api/orders` | ✅ | Listar órdenes (filtros: status, customer, fecha, usuario) |
| POST | `/api/orders` | ✅ | Crear orden con sus items |
| GET | `/api/orders/{id}` | ✅ | Detalle completo (items, pagos, envío, producción) |
| PATCH | `/api/orders/{id}/status` | ✅ | Cambiar estado de la orden |
| DELETE | `/api/orders/{id}` | ✅ | Cancelar orden (solo si está en `pending`) |
| GET | `/api/orders/{id}/items` | ✅ | Listar items de la orden |
| POST | `/api/orders/{id}/handlers` | ✅ | Registrar acción de un usuario sobre la orden |

#### Ciclo de estados
```
pending → confirmed → in_production → ready → shipped → delivered
                                                       ↘ cancelled
                                                       ↘ returned
```

#### Lógica de negocio
- Al crear orden: calcular `subtotal`, `line_profit` y `total_amount` en el servidor (nunca confiar en el frontend).
- Hacer snapshot de `product_name`, `unit_cost`, `unit_price` al momento de la venta (el campo ya existe en `order_items`).
- Al confirmar: descontar `qty_reserved` del inventario.
- Al cancelar: liberar `qty_reserved`.
- Registrar automáticamente en `order_handlers` cada cambio de estado.
- `expected_delivery_date` puede calcularse automáticamente: `created_at + production_lead_time_days` del producto con mayor lead time.

#### Archivos a crear
```
app/Http/Controllers/Api/OrderController.php
app/Http/Requests/Order/StoreOrderRequest.php
app/Http/Requests/Order/UpdateOrderStatusRequest.php
app/Http/Resources/OrderResource.php
app/Http/Resources/OrderItemResource.php
app/Services/OrderService.php       ← creación de orden + cálculo de totales
app/Services/InventoryService.php   ← (ya planificado en Módulo 7)
```

---

### Módulo 9 — Pagos 🔶
**Tabla:** `payments`  
**Prioridad:** ALTA — sigue lógicamente al Módulo 8  
**Descripción:** Registro de pagos asociados a órdenes. Soporta múltiples métodos de pago y pagos parciales.

#### Endpoints a implementar

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/api/orders/{id}/payments` | ✅ | Listar pagos de una orden |
| POST | `/api/orders/{id}/payments` | ✅ | Registrar pago (efectivo, transferencia, etc.) |
| PATCH | `/api/orders/{id}/payments/{pid}/status` | ✅ | Actualizar estado del pago |
| GET | `/api/payments` | ✅ | Listar todos los pagos (con filtros de estado/fecha) |

#### Lógica de negocio
- Un pedido puede tener múltiples pagos (abono inicial + saldo).
- Calcular monto pendiente: `order.total_amount - SUM(payments WHERE status='completed')`.
- Al completar el pago total → disparar transición de orden a `confirmed` si estaba `pending`.
- Registrar `transaction_id` cuando el pago es electrónico.
- No permitir `refund` sin una devolución aprobada en `order_returns`.

#### Archivos a crear
```
app/Http/Controllers/Api/PaymentController.php
app/Http/Requests/Payment/StorePaymentRequest.php
app/Http/Resources/PaymentResource.php
app/Services/PaymentService.php
```

---

### Módulo 10 — Devoluciones 🔶
**Tabla:** `order_returns`  
**Prioridad:** MEDIA  
**Descripción:** Gestión de solicitudes de devolución (total o parcial) con aprobación/rechazo y monto a reembolsar.

#### Endpoints a implementar

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/api/returns` | ✅ | Listar devoluciones (filtro por estado) |
| POST | `/api/orders/{id}/returns` | ✅ | Solicitar devolución |
| GET | `/api/returns/{id}` | ✅ | Ver detalle de devolución |
| PATCH | `/api/returns/{id}/status` | ✅ | Aprobar / Rechazar / Resolver |

#### Ciclo de estados
```
requested → approved → resolved
          → rejected
```

#### Lógica de negocio
- Solo se puede solicitar devolución si la orden está en `delivered` o `returned`.
- `return_type = 'partial'` requiere que se especifique `order_item_id`.
- Al aprobar una devolución: crear automáticamente un pago con `status = 'refunded'`.
- Al resolver una devolución parcial: reponer `qty_available` en inventario.
- Cambiar `order.status` a `returned` al aprobar devolución total.

#### Archivos a crear
```
app/Http/Controllers/Api/OrderReturnController.php
app/Http/Requests/Return/StoreReturnRequest.php
app/Http/Requests/Return/UpdateReturnStatusRequest.php
app/Http/Resources/OrderReturnResource.php
```

---

### Módulo 11 — Logística & Envíos 🔶
**Tabla:** `shipments`  
**Prioridad:** MEDIA-ALTA  
**Descripción:** Tracking de envíos, asignación de courier, gestión de entregas fallidas.

#### Endpoints a implementar

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/api/shipments` | ✅ | Listar envíos (filtro por status, courier, fecha) |
| POST | `/api/orders/{id}/shipments` | ✅ | Crear envío para una orden |
| GET | `/api/shipments/{id}` | ✅ | Ver detalle del envío |
| PATCH | `/api/shipments/{id}/status` | ✅ | Actualizar estado del envío |
| GET | `/api/orders/{id}/shipment` | ✅ | Envío activo de una orden |

#### Ciclo de estados
```
preparing → shipped → in_transit → delivered
                                  → failed
```

#### Lógica de negocio
- Solo se puede crear envío si `order.status = 'ready'`.
- Al crear envío: cambiar `order.status` a `shipped`.
- Al marcar `delivered`: cambiar `order.status` a `delivered`, registrar `delivered_at`.
- Si el envío falla (`failed`): requerir `failure_reason`, no cambiar el estado de la orden automáticamente.
- `handler_id` = usuario del sistema que gestionó el envío.

#### Archivos a crear
```
app/Http/Controllers/Api/ShipmentController.php
app/Http/Requests/Shipment/StoreShipmentRequest.php
app/Http/Requests/Shipment/UpdateShipmentStatusRequest.php
app/Http/Resources/ShipmentResource.php
```

---

### Módulo 12 — Producción 🔶
**Tabla:** `production_orders`  
**Prioridad:** MEDIA  
**Descripción:** Gestión de órdenes de producción por ítem de pedido. Permite asignar trabajadores y trackear el avance.

#### Endpoints a implementar

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/api/production-orders` | ✅ | Listar órdenes de producción (filtro: status, worker) |
| POST | `/api/production-orders` | ✅ | Crear orden de producción para un order_item |
| GET | `/api/production-orders/{id}` | ✅ | Ver detalle |
| PATCH | `/api/production-orders/{id}/status` | ✅ | Cambiar estado (iniciar, completar, cancelar) |
| PATCH | `/api/production-orders/{id}/assign` | ✅ | Asignar/reasignar trabajador |

#### Ciclo de estados
```
queued → in_progress → completed
       → cancelled
```

#### Lógica de negocio
- Se crea una `production_order` por cada `order_item` cuando la orden pasa a `in_production`.
- Al iniciar (`in_progress`): registrar `started_at`, actualizar `inventory.qty_in_production += qty`.
- Al completar: registrar `completed_at`, actualizar `inventory.qty_in_production -= qty`.
- Cuando todos los items de una orden estén completados: cambiar `order.status` a `ready` automáticamente.
- `assigned_worker_id` debe ser un usuario con rol apropiado (ej: `worker` / `operario`).

#### Archivos a crear
```
app/Http/Controllers/Api/ProductionOrderController.php
app/Http/Requests/Production/StoreProductionOrderRequest.php
app/Http/Requests/Production/UpdateProductionStatusRequest.php
app/Http/Resources/ProductionOrderResource.php
app/Services/ProductionService.php
```

---

### Módulo 13 — Dashboard & Reportes ❌
**Prioridad:** BAJA (implementar al final)  
**Descripción:** Endpoints de agregación para el panel de administración.

#### Endpoints a implementar

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| GET | `/api/dashboard/summary` | ✅ | KPIs generales (ventas hoy, pedidos activos, stock bajo) |
| GET | `/api/dashboard/sales` | ✅ | Ventas por período (día/semana/mes/año) |
| GET | `/api/dashboard/top-products` | ✅ | Productos más vendidos |
| GET | `/api/dashboard/orders-by-status` | ✅ | Distribución de órdenes por estado |
| GET | `/api/reports/inventory` | ✅ | Reporte de inventario (stock, reservado, en producción) |
| GET | `/api/reports/profitability` | ✅ | Rentabilidad por producto (`line_profit` agregado) |

#### Lógica de negocio
- Usar Query Builder con agregaciones en lugar de cargar colecciones Eloquent grandes.
- Añadir parámetros `from` y `to` (fechas) para filtrar rangos.
- Respuestas deben incluir comparación con el período anterior (delta %).
- Aplicar caché de 5 min en endpoints de alta carga (`/dashboard/summary`).

#### Archivos a crear
```
app/Http/Controllers/Api/DashboardController.php
app/Http/Controllers/Api/ReportController.php
```

---

## Convenciones Técnicas

### Estructura de Respuesta API
Todas las respuestas deben seguir el formato:
```json
{
  "success": true,
  "data": { ... },
  "message": "Operación exitosa",
  "meta": { "current_page": 1, "total": 50 }
}
```

### Autorización
- Usar Gates o Policies de Laravel para controlar acceso por rol.
- El middleware `auth:sanctum` ya está aplicado al grupo principal.
- Implementar middleware `CheckPermission` a futuro si se requiere granularidad por permiso.

### Validaciones
- Toda entrada de datos debe pasar por un `FormRequest` dedicado.
- Errores de validación retornan `422 Unprocessable Entity`.
- Errores de negocio (ej: stock insuficiente) retornan `409 Conflict` o `422` con mensaje claro.

### Servicios (Service Layer)
Para módulos con lógica de negocio compleja usar la capa `app/Services/`:
- `InventoryService` — ajuste atómico de stock
- `OrderService` — creación de orden, cálculo de totales, transiciones de estado
- `PaymentService` — registro y validación de pagos
- `ProductionService` — creación automática de órdenes de producción

### Transacciones de Base de Datos
Cualquier operación que toque múltiples tablas debe usar `DB::transaction()`:
```php
DB::transaction(function () {
    // crear orden
    // crear items
    // reservar inventario
});
```

### Paginación
Usar `->paginate(20)` en todos los endpoints de listado. Nunca `->get()` sin límite.

---

## Orden de Implementación Recomendado

```
Semana 1:  Módulo 6 (Customers) + Módulo 7 (Inventario)
Semana 2:  Módulo 8 (Órdenes) — el más complejo
Semana 3:  Módulo 9 (Pagos) + Módulo 10 (Devoluciones)
Semana 4:  Módulo 11 (Envíos) + Módulo 12 (Producción)
Semana 5:  Módulo 13 (Dashboard) + tests Feature + documentación
```

### Dependencias entre módulos
```
Customers (6)
    └── Orders (8)
            ├── Payments (9)
            ├── Returns (10)
            ├── Shipments (11)
            └── Production (12)
                    └── Inventory (7)

Dashboard (13) depende de todos los anteriores
```

---

## Testing

Cada módulo debe tener su suite de Feature Tests en `tests/Feature/`:

```
tests/Feature/
├── Auth/           ← ya existe (mejorar cobertura)
├── Customer/
│   ├── CreateCustomerTest.php
│   └── ListCustomersTest.php
├── Order/
│   ├── CreateOrderTest.php
│   ├── OrderStatusTransitionTest.php
│   └── OrderInventoryReservationTest.php
├── Payment/
│   └── RegisterPaymentTest.php
└── Inventory/
    └── StockAdjustmentTest.php
```

Comando de ejecución: `php artisan test --parallel`
