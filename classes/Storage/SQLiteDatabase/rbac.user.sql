CREATE TABLE `rbac.user` (
`id` INTEGER NOT NULL, 
`roles` TEXT NOT NULL, 
`group` CHAR(32) NOT NULL, 
`permissions` TEXT NOT NULL, 
`exists` INTEGER NOT NULL,
`ban` TEXT,
PRIMARY KEY (`id`)
 )