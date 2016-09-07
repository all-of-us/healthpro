CREATE TABLE `demo` (
  `state` varchar(2) NOT NULL,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`state`)
) DEFAULT CHARSET=utf8mb4;

INSERT INTO `demo` (`state`, `value`) VALUES
('CA', 5),
('MA', 25),
('MD', 10),
('TN', 10);
