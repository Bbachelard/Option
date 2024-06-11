
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- option_product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `option_product`;

CREATE TABLE `option_product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `fi_option_product_product_id` (`product_id`),
    CONSTRAINT `fk_option_product_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product_available_option
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_available_option`;

CREATE TABLE `product_available_option`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `option_id` INTEGER NOT NULL,
    `price` DECIMAL(16,6) DEFAULT 0.000000,
    `promo_price` DECIMAL(16,6) DEFAULT 0.000000,
    `is_promo` TINYINT(1) DEFAULT 0,
    `option_added_by` JSON,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `product_available_option_UNIQUE` (`product_id`, `option_id`),
    INDEX `idx_product_available_option_product_id` (`product_id`),
    INDEX `idx_product_available_option_option_id` (`option_id`),
    CONSTRAINT `fk_product_available_option_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_available_option_option_id`
        FOREIGN KEY (`option_id`)
        REFERENCES `option_product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- category_available_option
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_available_option`;

CREATE TABLE `category_available_option`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `category_id` INTEGER NOT NULL,
    `option_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `category_available_option_UNIQUE` (`category_id`, `option_id`),
    INDEX `idx_category_available_option_category_id` (`category_id`),
    INDEX `idx_category_available_option_option_id` (`option_id`),
    CONSTRAINT `fk_category_available_option_product_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_category_available_option_option_id`
        FOREIGN KEY (`option_id`)
        REFERENCES `option_product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- template_available_option
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `template_available_option`;

CREATE TABLE `template_available_option`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `template_id` INTEGER NOT NULL,
    `option_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `template_available_option_UNIQUE` (`template_id`, `option_id`),
    INDEX `idx_template_available_option_template_id` (`template_id`),
    INDEX `idx_template_available_option_option_id` (`option_id`),
    CONSTRAINT `fk_template_available_option_product_id`
        FOREIGN KEY (`template_id`)
        REFERENCES `template` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_template_available_option_option_id`
        FOREIGN KEY (`option_id`)
        REFERENCES `option_product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- option_cart_item_order_product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `option_cart_item_order_product`;

CREATE TABLE `option_cart_item_order_product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_available_option_id` INTEGER,
    `cart_item_option_id` INTEGER,
    `order_product_id` INTEGER,
    `option_order_product_id` INTEGER,
    `customization_data` TEXT,
    `price` DECIMAL(16,6) DEFAULT 0.000000,
    `taxed_price` DECIMAL(16,6) DEFAULT 0.000000,
    `quantity` VARCHAR(255),
    PRIMARY KEY (`id`),
    INDEX `fi_option_cart_item_order_product_cioid` (`cart_item_option_id`),
    INDEX `fi_option_cart_item_order_product_paoid` (`product_available_option_id`),
    INDEX `fi_option_cart_item_order_product_opid` (`order_product_id`),
    INDEX `fi_option_cart_item_order_product_oopid` (`option_order_product_id`),
    CONSTRAINT `fk_option_cart_item_order_product_cioid`
        FOREIGN KEY (`cart_item_option_id`)
        REFERENCES `cart_item` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_option_cart_item_order_product_paoid`
        FOREIGN KEY (`product_available_option_id`)
        REFERENCES `product_available_option` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_option_cart_item_order_product_opid`
        FOREIGN KEY (`order_product_id`)
        REFERENCES `order_product` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_option_cart_item_order_product_oopid`
        FOREIGN KEY (`option_order_product_id`)
        REFERENCES `order_product` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
