-- Valūtu konvertētāja datubāzes shēma (MySQL / MariaDB, dzinējs InnoDB)
-- Šo failu var palaist HeidiSQL: izvēlieties savu jauno datubāzi, tad File > Run SQL file.

SET NAMES utf8mb4;

-- Lietotāji (autorizācija). Parole tiek glabāta tikai kā jaucējkods (password_hash).
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(30)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    email         VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, -- nav obligāts (datu minimizēšana)
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT chk_users_username CHECK (CHAR_LENGTH(username) BETWEEN 3 AND 30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valūtu saraksts (ISO 4217 trīsburtu kodi, tikai lielie burti)
CREATE TABLE currencies (
    code CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (code),
    CONSTRAINT chk_currencies_code CHECK (code REGEXP '^[A-Z]{3}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kursu vēsture: cik vienību valūtas atbilst 1 EUR konkrētā datumā (EUR rindas netiek glabātas, tā ir bāzes valūta)
CREATE TABLE exchange_rates (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rate_date     DATE NOT NULL,
    currency_code CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    rate          DECIMAL(18,8) NOT NULL,
    source        VARCHAR(20) NOT NULL DEFAULT 'frankfurter',   -- 'frankfurter' vai 'demo'
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_day_currency (rate_date, currency_code),
    KEY idx_rates_currency_date (currency_code, rate_date),
    CONSTRAINT fk_rates_currency FOREIGN KEY (currency_code) REFERENCES currencies (code),
    CONSTRAINT chk_rates_positive CHECK (rate > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lietotāju veiktās konvertācijas (personīgā vēsture)
CREATE TABLE conversions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    from_code  CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    to_code    CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    amount     DECIMAL(18,2) NOT NULL,
    result     DECIMAL(24,2) NOT NULL,
    rate_used  DECIMAL(18,8) NOT NULL,
    rate_date  DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_conversions_user (user_id, id),
    CONSTRAINT fk_conv_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_from FOREIGN KEY (from_code) REFERENCES currencies (code),
    CONSTRAINT fk_conv_to   FOREIGN KEY (to_code)   REFERENCES currencies (code),
    CONSTRAINT chk_conv_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Neveiksmīgie pieteikšanās mēģinājumi (aizsardzība pret paroļu minēšanu)
CREATE TABLE login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username     VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
    ip           VARCHAR(45)  NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_lookup (username, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
