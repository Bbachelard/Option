SET FOREIGN_KEY_CHECKS = 0;
alter table product_available_option add price DECIMAL(6,2);
alter table product_available_option add promo_price DECIMAL(6,2);
alter table product_available_option ADD COLUMN is_promo TinyInt(1);
SET FOREIGN_KEY_CHECKS = 1;