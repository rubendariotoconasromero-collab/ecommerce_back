-- ============================================================
--  ECOMMERCE + PRODUCCIÓN + ENTREGAS — MySQL Schema
--  Versión corregida con separación de Clientes y Usuarios
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. PERMISSIONS
-- ------------------------------------------------------------
CREATE TABLE permissions (
    id            CHAR(36)     NOT NULL DEFAULT (UUID()),
    name          VARCHAR(100) NOT NULL,
    slug          VARCHAR(100) NOT NULL UNIQUE,
    module        VARCHAR(100) NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. ROLES
-- ------------------------------------------------------------
CREATE TABLE roles (
    id            CHAR(36)     NOT NULL DEFAULT (UUID()),
    name          VARCHAR(100) NOT NULL,
    slug          VARCHAR(100) NOT NULL UNIQUE,
    description   TEXT,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. ROLE_PERMISSION  (tabla pivote N:M)
-- ------------------------------------------------------------
CREATE TABLE role_permission (
    role_id       CHAR(36) NOT NULL,
    permission_id CHAR(36) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. USERS  (Identidades de Acceso y Personal)
-- ------------------------------------------------------------
CREATE TABLE users (
    id            CHAR(36)      NOT NULL DEFAULT (UUID()),
    role_id       CHAR(36)      NOT NULL,
    name          VARCHAR(150)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    phone         VARCHAR(30),
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4.5. CUSTOMERS  (Perfiles Comerciales y Clientes B2B/B2C)
-- ------------------------------------------------------------
CREATE TABLE customers (
    id            CHAR(36)      NOT NULL DEFAULT (UUID()),
    user_id       CHAR(36)      NULL, -- Opcional: Relación con cuenta de acceso si está registrado
    customer_type ENUM('individual','business') NOT NULL DEFAULT 'individual',
    name          VARCHAR(150)  NOT NULL, -- Nombre de contacto o razón social
    email         VARCHAR(150)  NOT NULL UNIQUE,
    business_name VARCHAR(150)  NULL, -- Razón social en caso de ser de tipo business
    tax_id        VARCHAR(50)   NULL, -- NIT / RUT / RFC / etc.
    phone         VARCHAR(30),
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. CATEGORIES
-- ------------------------------------------------------------
CREATE TABLE categories (
    id            CHAR(36)     NOT NULL DEFAULT (UUID()),
    parent_id     CHAR(36),                          -- soporte para subcategorías
    name          VARCHAR(100) NOT NULL,
    slug          VARCHAR(100) NOT NULL UNIQUE,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. PRODUCTS
-- ------------------------------------------------------------
CREATE TABLE products (
    id                        CHAR(36)       NOT NULL DEFAULT (UUID()),
    category_id               CHAR(36)       NOT NULL,
    sku                       VARCHAR(100)   NOT NULL UNIQUE,
    name                      VARCHAR(200)   NOT NULL,
    description               TEXT,
    base_price                DECIMAL(12,2)  NOT NULL,
    cost_price                DECIMAL(12,2)  NOT NULL,
    sale_price                DECIMAL(12,2)  NOT NULL,
    production_lead_time_days SMALLINT       NOT NULL DEFAULT 0,
    attributes                JSON,
    is_active                 TINYINT(1)     NOT NULL DEFAULT 1,
    created_at                TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 7. PRODUCTS_IMAGES
-- ------------------------------------------------------------
CREATE TABLE products_images (
    id          CHAR(36)     NOT NULL DEFAULT (UUID()),
    product_id  CHAR(36)     NOT NULL,
    image_path  VARCHAR(500) NOT NULL,
    is_primary  TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_pimages_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 8. INVENTORY  (stock por producto)
-- ------------------------------------------------------------
CREATE TABLE inventory (
    id               CHAR(36)    NOT NULL DEFAULT (UUID()),
    product_id       CHAR(36)    NOT NULL UNIQUE,
    qty_available    INT         NOT NULL DEFAULT 0,
    qty_reserved     INT         NOT NULL DEFAULT 0,  -- reservado por órdenes activas
    qty_in_production INT        NOT NULL DEFAULT 0,
    reorder_point    INT         NOT NULL DEFAULT 0,
    updated_at       TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 9. ORDERS
-- ------------------------------------------------------------
CREATE TABLE orders (
    id                     CHAR(36)     NOT NULL DEFAULT (UUID()),
    customer_id            CHAR(36)     NOT NULL, -- El cliente que compra y a quien se le factura
    user_id                CHAR(36)     NULL,     -- El usuario que ejecutó la compra (opcional)
    total_amount           DECIMAL(12,2) NOT NULL,
    status                 ENUM('pending','confirmed','in_production','ready','shipped','delivered','cancelled','returned')
                           NOT NULL DEFAULT 'pending',
    expected_delivery_date DATE,
    shipping_address       TEXT         NOT NULL,
    notes                  TEXT,
    created_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_orders_user     FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 10. ORDER_ITEMS
-- ------------------------------------------------------------
CREATE TABLE order_items (
    id                   CHAR(36)      NOT NULL DEFAULT (UUID()),
    order_id             CHAR(36)      NOT NULL,
    product_id           CHAR(36)      NOT NULL,
    product_name         VARCHAR(200)  NOT NULL,   -- snapshot al momento de la venta
    quantity             INT           NOT NULL,
    unit_cost            DECIMAL(12,2) NOT NULL,
    unit_price           DECIMAL(12,2) NOT NULL,
    subtotal             DECIMAL(12,2) NOT NULL,
    line_profit          DECIMAL(12,2) NOT NULL,
    customization_notes  TEXT,
    reference_image_path VARCHAR(500),
    created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_oi_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 11. PAYMENTS
-- ------------------------------------------------------------
CREATE TABLE payments (
    id             CHAR(36)      NOT NULL DEFAULT (UUID()),
    order_id       CHAR(36)      NOT NULL,
    payment_method VARCHAR(80)   NOT NULL,
    transaction_id VARCHAR(200),
    amount         DECIMAL(12,2) NOT NULL,
    status         ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    paid_at        TIMESTAMP,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 12. ORDER_RETURNS
-- ------------------------------------------------------------
CREATE TABLE order_returns (
    id            CHAR(36)      NOT NULL DEFAULT (UUID()),
    order_id      CHAR(36)      NOT NULL,
    order_item_id CHAR(36),
    return_type   ENUM('full','partial') NOT NULL DEFAULT 'full',
    status        ENUM('requested','approved','rejected','resolved') NOT NULL DEFAULT 'requested',
    reason        TEXT,
    refund_amount DECIMAL(12,2),
    request_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at   TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_returns_order      FOREIGN KEY (order_id)      REFERENCES orders(id),
    CONSTRAINT fk_returns_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 13. ORDER_HANDLERS
-- ------------------------------------------------------------
CREATE TABLE order_handlers (
    id           CHAR(36)    NOT NULL DEFAULT (UUID()),
    order_id     CHAR(36)    NOT NULL,
    user_id      CHAR(36)    NOT NULL,
    handler_name VARCHAR(150) NOT NULL,
    handler_role VARCHAR(100) NOT NULL,
    action_taken TEXT        NOT NULL,
    notes        TEXT,
    handled_at   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_oh_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_oh_user  FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 14. SHIPMENTS
-- ------------------------------------------------------------
CREATE TABLE shipments (
    id               CHAR(36)     NOT NULL DEFAULT (UUID()),
    order_id         CHAR(36)     NOT NULL,
    tracking_number  VARCHAR(100),
    courier_name     VARCHAR(100),
    handler_id       CHAR(36),                     -- quién gestionó el envío (usuario)
    status           ENUM('preparing','shipped','in_transit','delivered','failed') NOT NULL DEFAULT 'preparing',
    failure_reason   TEXT,
    shipped_at       TIMESTAMP,
    delivered_at     TIMESTAMP,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_shipments_order   FOREIGN KEY (order_id)   REFERENCES orders(id),
    CONSTRAINT fk_shipments_handler FOREIGN KEY (handler_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 15. PRODUCTION_ORDERS
-- ------------------------------------------------------------
CREATE TABLE production_orders (
    id                 CHAR(36)  NOT NULL DEFAULT (UUID()),
    order_item_id      CHAR(36)  NOT NULL,
    assigned_worker_id CHAR(36),
    status             ENUM('queued','in_progress','completed','cancelled') NOT NULL DEFAULT 'queued',
    started_at         TIMESTAMP,
    completed_at       TIMESTAMP,
    internal_notes     TEXT,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_po_order_item FOREIGN KEY (order_item_id)      REFERENCES order_items(id),
    CONSTRAINT fk_po_worker     FOREIGN KEY (assigned_worker_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  ÍNDICES ADICIONALES PARA PERFORMANCE
-- ============================================================
CREATE INDEX idx_orders_customer_id    ON orders(customer_id);
CREATE INDEX idx_orders_user_id        ON orders(user_id);
CREATE INDEX idx_orders_status         ON orders(status);
CREATE INDEX idx_order_items_order_id  ON order_items(order_id);
CREATE INDEX idx_order_items_product   ON order_items(product_id);
CREATE INDEX idx_payments_order_id     ON payments(order_id);
CREATE INDEX idx_payments_status       ON payments(status);
CREATE INDEX idx_shipments_order_id    ON shipments(order_id);
CREATE INDEX idx_production_item_id    ON production_orders(order_item_id);
CREATE INDEX idx_production_status     ON production_orders(status);
CREATE INDEX idx_products_category     ON products(category_id);
CREATE INDEX idx_products_sku          ON products(sku);
CREATE INDEX idx_users_email           ON users(email);
CREATE INDEX idx_users_role            ON users(role_id);
CREATE INDEX idx_customers_user_id     ON customers(user_id);
CREATE INDEX idx_customers_email       ON customers(email);