-- ============================================================
--  ECOMMERCE + PRODUCCIÓN + ENTREGAS — Esquema MySQL
--  Versión en Español — Totalmente funcional y traducida
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. PERMISOS (permissions)
-- ------------------------------------------------------------
CREATE TABLE permisos (
    id            CHAR(36)     NOT NULL DEFAULT (UUID()),
    nombre        VARCHAR(100) NOT NULL,
    slug          VARCHAR(100) NOT NULL UNIQUE,
    modulo        VARCHAR(100) NOT NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. ROLES (roles)
-- ------------------------------------------------------------
CREATE TABLE roles (
    id            CHAR(36)     NOT NULL DEFAULT (UUID()),
    nombre        VARCHAR(100) NOT NULL,
    slug          VARCHAR(100) NOT NULL UNIQUE,
    descripcion   TEXT,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. ROL_PERMISO (role_permission — tabla pivote N:M)
-- ------------------------------------------------------------
CREATE TABLE rol_permiso (
    rol_id        CHAR(36)  NOT NULL,
    permiso_id    CHAR(36)  NOT NULL,
    creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rp_rol     FOREIGN KEY (rol_id)     REFERENCES roles(id)     ON DELETE CASCADE,
    CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. USUARIOS (users — Identidades de acceso y personal interno)
-- ------------------------------------------------------------
CREATE TABLE usuarios (
    id             CHAR(36)      NOT NULL DEFAULT (UUID()),
    rol_id         CHAR(36)      NOT NULL,
    nombre         VARCHAR(150)  NOT NULL,
    correo         VARCHAR(150)  NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    telefono       VARCHAR(30),
    activo         TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4.5. CLIENTES (customers — Perfiles comerciales y facturación B2B/B2C)
-- ------------------------------------------------------------
CREATE TABLE clientes (
    id                    CHAR(36)      NOT NULL DEFAULT (UUID()),
    usuario_id            CHAR(36)      NULL, -- Opcional: Relación con cuenta de acceso si está registrado
    tipo_cliente          ENUM('individual','empresa') NOT NULL DEFAULT 'individual',
    nombre                VARCHAR(150)  NOT NULL, -- Nombre de contacto o del cliente
    correo                VARCHAR(150)  NOT NULL UNIQUE,
    razon_social          VARCHAR(150)  NULL, -- Obligatorio solo para tipo_cliente = 'empresa'
    identificacion_fiscal VARCHAR(50)   NULL, -- NIT / RUT / RFC / etc.
    telefono              VARCHAR(30),
    activo                TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_clientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. CATEGORIAS (categories)
-- ------------------------------------------------------------
CREATE TABLE categorias (
    id             CHAR(36)     NOT NULL DEFAULT (UUID()),
    padre_id       CHAR(36),                          -- soporte para subcategorías
    nombre         VARCHAR(100) NOT NULL,
    slug           VARCHAR(100) NOT NULL UNIQUE,
    activo         TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_categorias_padre FOREIGN KEY (padre_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. PRODUCTOS (products)
-- ------------------------------------------------------------
CREATE TABLE productos (
    id                      CHAR(36)       NOT NULL DEFAULT (UUID()),
    categoria_id            CHAR(36)       NOT NULL,
    sku                     VARCHAR(100)   NOT NULL UNIQUE,
    nombre                  VARCHAR(200)   NOT NULL,
    descripcion             TEXT,
    precio_base             DECIMAL(12,2)  NOT NULL,
    precio_costo            DECIMAL(12,2)  NOT NULL,
    precio_venta            DECIMAL(12,2)  NOT NULL,
    dias_tiempo_produccion  SMALLINT       NOT NULL DEFAULT 0,
    atributos               JSON,
    activo                  TINYINT(1)     NOT NULL DEFAULT 1,
    creado_en               TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 7. IMAGENES_PRODUCTOS (products_images)
-- ------------------------------------------------------------
CREATE TABLE imagenes_productos (
    id           CHAR(36)     NOT NULL DEFAULT (UUID()),
    producto_id  CHAR(36)     NOT NULL,
    ruta_imagen  VARCHAR(500) NOT NULL,
    es_principal TINYINT(1)   NOT NULL DEFAULT 0,
    orden        SMALLINT     NOT NULL DEFAULT 0,
    creado_en    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_iprog_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 8. INVENTARIO (inventory — stock por producto)
-- ------------------------------------------------------------
CREATE TABLE inventario (
    id                     CHAR(36)    NOT NULL DEFAULT (UUID()),
    producto_id            CHAR(36)    NOT NULL UNIQUE,
    cantidad_disponible    INT         NOT NULL DEFAULT 0,
    cantidad_reservada     INT         NOT NULL DEFAULT 0, -- reservada por órdenes activas
    cantidad_en_produccion INT         NOT NULL DEFAULT 0,
    punto_reorden          INT         NOT NULL DEFAULT 0,
    actualizado_en         TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_inventario_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 9. PEDIDOS (orders)
-- ------------------------------------------------------------
CREATE TABLE pedidos (
    id                     CHAR(36)     NOT NULL DEFAULT (UUID()),
    cliente_id             CHAR(36)     NOT NULL, -- El cliente que compra y a quien se le factura
    usuario_id             CHAR(36)     NULL,     -- El usuario de acceso que ejecutó la compra (opcional)
    monto_total            DECIMAL(12,2) NOT NULL,
    estado                 ENUM('pendiente','confirmado','en_produccion','listo','enviado','entregado','cancelado','devuelto')
                           NOT NULL DEFAULT 'pendiente',
    fecha_entrega_esperada DATE,
    direccion_envio        TEXT         NOT NULL,
    notas                  TEXT,
    creado_en              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_pedidos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 10. DETALLES_PEDIDO (order_items)
-- ------------------------------------------------------------
CREATE TABLE detalles_pedido (
    id                      CHAR(36)      NOT NULL DEFAULT (UUID()),
    pedido_id               CHAR(36)      NOT NULL,
    producto_id             CHAR(36)      NOT NULL,
    nombre_producto         VARCHAR(200)  NOT NULL, -- instantánea al momento de la venta
    cantidad                INT           NOT NULL,
    costo_unitario          DECIMAL(12,2) NOT NULL,
    precio_unitario         DECIMAL(12,2) NOT NULL,
    subtotal                DECIMAL(12,2) NOT NULL,
    ganancia_linea          DECIMAL(12,2) NOT NULL,
    notas_personalizacion   TEXT,
    ruta_imagen_referencia  VARCHAR(500),
    creado_en               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_dp_pedido   FOREIGN KEY (pedido_id)   REFERENCES pedidos(id)   ON DELETE CASCADE,
    CONSTRAINT fk_dp_producto FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 11. PAGOS (payments)
-- ------------------------------------------------------------
CREATE TABLE pagos (
    id             CHAR(36)      NOT NULL DEFAULT (UUID()),
    pedido_id      CHAR(36)      NOT NULL,
    metodo_pago    VARCHAR(80)   NOT NULL,
    id_transaccion VARCHAR(200),
    monto          DECIMAL(12,2) NOT NULL,
    estado         ENUM('pendiente','completado','fallido','reembolsado') NOT NULL DEFAULT 'pendiente',
    pagado_en      TIMESTAMP,
    creado_en      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_pagos_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 12. DEVOLUCIONES_PEDIDO (order_returns)
-- ------------------------------------------------------------
CREATE TABLE devoluciones_pedido (
    id                CHAR(36)      NOT NULL DEFAULT (UUID()),
    pedido_id         CHAR(36)      NOT NULL,
    detalle_pedido_id CHAR(36),
    tipo_devolucion   ENUM('completa','parcial') NOT NULL DEFAULT 'completa',
    estado            ENUM('solicitada','aprobada','rechazada','resuelta') NOT NULL DEFAULT 'solicitada',
    motivo            TEXT,
    monto_reembolso   DECIMAL(12,2),
    solicitado_en     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resuelto_en       TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_devoluciones_pedido  FOREIGN KEY (pedido_id)         REFERENCES pedidos(id),
    CONSTRAINT fk_devoluciones_detalle FOREIGN KEY (detalle_pedido_id) REFERENCES detalles_pedido(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 13. GESTIONES_PEDIDO (order_handlers)
-- ------------------------------------------------------------
CREATE TABLE gestiones_pedido (
    id             CHAR(36)     NOT NULL DEFAULT (UUID()),
    pedido_id      CHAR(36)     NOT NULL,
    usuario_id     CHAR(36)     NOT NULL,
    nombre_gestor  VARCHAR(150) NOT NULL,
    rol_gestor     VARCHAR(100) NOT NULL,
    accion_tomada  TEXT         NOT NULL,
    notas          TEXT,
    gestionado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_gp_pedido  FOREIGN KEY (pedido_id)  REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_gp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 14. ENVIOS (shipments)
-- ------------------------------------------------------------
CREATE TABLE envios (
    id                  CHAR(36)     NOT NULL DEFAULT (UUID()),
    pedido_id           CHAR(36)     NOT NULL,
    numero_seguimiento  VARCHAR(100),
    nombre_transportista VARCHAR(100),
    gestor_id           CHAR(36),                     -- Quién gestionó el envío (usuario)
    estado              ENUM('preparando','enviado','en_transito','entregado','fallido') NOT NULL DEFAULT 'preparando',
    motivo_fallo        TEXT,
    enviado_en          TIMESTAMP,
    entregado_en        TIMESTAMP,
    creado_en           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_envios_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
    CONSTRAINT fk_envios_gestor FOREIGN KEY (gestor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 15. ORDENES_PRODUCCION (production_orders)
-- ------------------------------------------------------------
CREATE TABLE ordenes_produccion (
    id                  CHAR(36)  NOT NULL DEFAULT (UUID()),
    detalle_pedido_id   CHAR(36)  NOT NULL,
    operario_asignado_id CHAR(36),
    estado              ENUM('en_cola','en_progreso','completado','cancelado') NOT NULL DEFAULT 'en_cola',
    iniciado_en         TIMESTAMP,
    completado_en       TIMESTAMP,
    notas_internas      TEXT,
    creado_en           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_op_detalle  FOREIGN KEY (detalle_pedido_id)   REFERENCES detalles_pedido(id),
    CONSTRAINT fk_op_operario FOREIGN KEY (operario_asignado_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  ÍNDICES ADICIONALES PARA RENDIMIENTO
-- ============================================================
CREATE INDEX idx_pedidos_cliente_id       ON pedidos(cliente_id);
CREATE INDEX idx_pedidos_usuario_id       ON pedidos(usuario_id);
CREATE INDEX idx_pedidos_estado           ON pedidos(estado);
CREATE INDEX idx_detalles_pedido_id       ON detalles_pedido(pedido_id);
CREATE INDEX idx_detalles_producto_id     ON detalles_pedido(producto_id);
CREATE INDEX idx_pagos_pedido_id          ON pagos(pedido_id);
CREATE INDEX idx_pagos_estado             ON pagos(estado);
CREATE INDEX idx_envios_pedido_id         ON envios(pedido_id);
CREATE INDEX idx_produccion_detalle_id    ON ordenes_produccion(detalle_pedido_id);
CREATE INDEX idx_produccion_estado        ON ordenes_produccion(estado);
CREATE INDEX idx_productos_categoria_id   ON productos(categoria_id);
CREATE INDEX idx_productos_sku            ON productos(sku);
CREATE INDEX idx_usuarios_correo          ON usuarios(correo);
CREATE INDEX idx_usuarios_rol_id          ON usuarios(rol_id);
CREATE INDEX idx_clientes_usuario_id      ON clientes(usuario_id);
CREATE INDEX idx_clientes_correo          ON clientes(correo);
