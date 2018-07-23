ALTER TABLE orders DROP INDEX order_id;
ALTER TABLE orders ADD UNIQUE (order_id);
