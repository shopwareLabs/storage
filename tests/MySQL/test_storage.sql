CREATE TABLE `test_storage` (
    `key` varchar(255) NOT NULL,
    `stringField` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `textField` longtext COLLATE utf8mb4_unicode_ci,
    `dateField` datetime(3) DEFAULT NULL,
    `intField` int DEFAULT NULL,
    `boolField` tinyint(1) DEFAULT NULL,
    `floatField` decimal(10, 4) DEFAULT NULL,
    `objectField` json DEFAULT NULL,
    `listField` json DEFAULT NULL,
    `objectListField` json DEFAULT NULL,
    `translatedString` json DEFAULT NULL,
    `translatedInt` json DEFAULT NULL,
    `translatedFloat` json DEFAULT NULL,
    `translatedBool` json DEFAULT NULL,
    `translatedDate` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

