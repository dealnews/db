CREATE TABLE IF NOT EXISTS `image_tags` (
  `image_tag_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `image_id` bigint(20) unsigned NOT NULL,
  `tag` varchar(255) NOT NULL,
  `wh_insert_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`image_tag_id`),
  UNIQUE KEY `image_tags` (`tag`,`image_id`),
  KEY `wh_insert_date` (`wh_insert_date`)
);