CREATE TABLE `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `site_id` varchar(255) NULL DEFAULT NULL,
  `organization_id` varchar(255) NULL DEFAULT NULL,
  `awardee_id` varchar(255) NULL DEFAULT NULL,
  `google_group` varchar(255) NOT NULL,
  `mayolink_account` varchar(255) DEFAULT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  `organization` varchar(100) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `awardee` varchar(100) DEFAULT NULL, 
  `email` varchar(512) DEFAULT NULL,
  `centrifuge_type` varchar(50) NULL DEFAULT NULL,
  `workqueue_download` varchar(50) NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;
