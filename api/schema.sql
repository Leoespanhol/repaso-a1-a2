-- Progreso de cada alumno por apostila. Ejecutar una sola vez en phpMyAdmin.
CREATE TABLE IF NOT EXISTS progreso_alumno (
  email          VARCHAR(190) NOT NULL,
  apostila       VARCHAR(50)  NOT NULL,
  nombre         VARCHAR(150) NOT NULL DEFAULT '',
  profesor       VARCHAR(150) NOT NULL DEFAULT '',
  datos          LONGTEXT     NOT NULL COMMENT 'JSON {values, screen, savedAt}',
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (email, apostila)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
