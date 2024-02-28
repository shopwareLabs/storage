DROP TABLE IF EXISTS `product`;

CREATE TABLE `product` (
    `key` varchar(255) NOT NULL,
    `ean` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `comment` longtext COLLATE utf8mb4_unicode_ci,
    `changed` datetime(3) DEFAULT NULL,
    `stock` int DEFAULT NULL,
    `active` tinyint(1) DEFAULT NULL,
    `price` decimal(10, 4) DEFAULT NULL,
    `mainCategory` json DEFAULT NULL,
    `keywords` json DEFAULT NULL,
    `categories` json DEFAULT NULL,
    `name` json DEFAULT NULL,
    `description` json DEFAULT NULL,
    `position` json DEFAULT NULL,
    `weight` json DEFAULT NULL,
    `highlight` json DEFAULT NULL,
    `release` json DEFAULT NULL,
    `tags` json DEFAULT NULL,
    FULLTEXT KEY `ean` (`ean`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

