CREATE TABLE `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `google_group` varchar(255) NOT NULL,
  `mayolink_account` varchar(255) NOT NULL,
  `timezone` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8mb4;

INSERT INTO `sites` (name, google_group, mayolink_account) VALUES ('Hogwarts', 'hogwarts-google-group', 'hogwarts-mayo');
INSERT INTO `sites` (name, google_group, mayolink_account) VALUES ('Durmstrang', 'durmstrang-google-group','durmstrang-mayo');
INSERT INTO `sites` (name, google_group, mayolink_account) VALUES ('Beauxbatons', 'beauxbatons-google-group','beauxbatons-mayo');