CREATE TABLE `rbac.role` (
`name` CHAR(64) NOT NULL, 
`description` CHAR(255), 
`permissions` TEXT, 
`roles` TEXT, 
`exists` INTEGER,
PRIMARY KEY (`name`)
 )