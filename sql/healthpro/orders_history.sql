CREATE TABLE `orders_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `site` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `created_ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`)
  REFERENCES orders(`id`)
) DEFAULT CHARSET=utf8mb4;
