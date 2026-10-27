
DROP TABLE IF EXISTS `{{prefix}}{{KEY}}`;
CREATE TABLE `{{prefix}}{{KEY}}` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键id',
  `site_id` int NOT NULL DEFAULT 0 COMMENT '站点id',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态:1启用.0禁用',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `IDX_{{KEY}}_site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci COMMENT='{{TITLE}}示例表';
