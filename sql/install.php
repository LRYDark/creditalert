<?php

class PluginCreditalertInstall
{
    /**
     * Install plugin database schema and defaults.
     *
     * @return bool
     */
    public static function install(): bool
    {
        global $DB;

        foreach (glob(__DIR__ . '/../inc/*.class.php') as $filepath) {
            if (preg_match("/inc.(.+)\.class.php/", $filepath) !== 0) {
                include_once $filepath;
            }
        }

        $migration = new Migration(PLUGIN_CREDITALERT_VERSION);

        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $keySign = DBConnection::getDefaultPrimaryKeySignOption();

        $configTable = 'glpi_plugin_creditalert_configs';
        if (!$DB->tableExists($configTable)) {
            $query = <<<SQL
                CREATE TABLE `$configTable` (
                    `id` int $keySign NOT NULL auto_increment,
                    `alert_threshold` int NOT NULL DEFAULT 80,
                    `notification_emails` text,
                    `credit_table` varchar(255) NOT NULL DEFAULT 'glpi_plugin_credit_entities',
                    `consumption_table` varchar(255) NOT NULL DEFAULT 'glpi_plugin_credit_tickets',
                    `field_sold` varchar(255) NOT NULL DEFAULT 'quantity',
                    `field_used` varchar(255) NOT NULL DEFAULT 'consumed',
                    `field_entity` varchar(255) NOT NULL DEFAULT 'entities_id',
                    `field_client` varchar(255) NOT NULL DEFAULT 'name',
                    `field_fk_usage` varchar(255) NOT NULL DEFAULT 'plugin_credit_entities_id',
                    `field_end_date` varchar(255) NOT NULL DEFAULT 'end_date',
                    `field_is_active` varchar(255) NOT NULL DEFAULT 'is_active',
                    `field_ticket` varchar(255) NOT NULL DEFAULT 'tickets_id',
                    `color_warning` varchar(20) NOT NULL DEFAULT '#f7c77d',
                    `color_over` varchar(20) NOT NULL DEFAULT '#f2958a',
                    `export_filename_base` varchar(255) NOT NULL DEFAULT 'Export_Client_Glpi',
                    `export_filename_include_date` tinyint NOT NULL DEFAULT '0',
                    `export_filename_include_entity` tinyint NOT NULL DEFAULT '0',
                    `last_cache_build` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;
SQL;
            $DB->doQuery($query);

            $DB->insert(
                $configTable,
                [
                    'alert_threshold'     => 80,
                    'notification_emails' => '',
                ]
            );
        } else {
            if (!$DB->fieldExists($configTable, 'field_ticket')) {
                $migration->addField($configTable, 'field_ticket', 'string', ['after' => 'field_is_active', 'value' => 'tickets_id']);
            }
            if (!$DB->fieldExists($configTable, 'export_filename_base')) {
                $migration->addField($configTable, 'export_filename_base', 'string', ['value' => 'Export_Client_Glpi']);
            }
            if (!$DB->fieldExists($configTable, 'export_filename_include_date')) {
                $migration->addField($configTable, 'export_filename_include_date', 'bool', ['value' => 0]);
            }
            if (!$DB->fieldExists($configTable, 'export_filename_include_entity')) {
                $migration->addField($configTable, 'export_filename_include_entity', 'bool', ['value' => 0]);
            }
        }

        $entityConfigTable = 'glpi_plugin_creditalert_entityconfigs';
        if (!$DB->tableExists($entityConfigTable)) {
            $query = <<<SQL
                CREATE TABLE `$entityConfigTable` (
                    `id` int $keySign NOT NULL auto_increment,
                    `entities_id` int $keySign NOT NULL DEFAULT '0',
                    `alert_threshold` int DEFAULT NULL,
                    `notification_emails` text,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `entities_id` (`entities_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;
SQL;
            $DB->doQuery($query);
        }

        $notificationTable = 'glpi_plugin_creditalert_notifications';
        if (!$DB->tableExists($notificationTable)) {
            $query = <<<SQL
                CREATE TABLE `$notificationTable` (
                    `id` int $keySign NOT NULL auto_increment,
                    `plugin_credit_entities_id` int $keySign NOT NULL,
                    `last_status` varchar(20) DEFAULT NULL,
                    `last_percentage` decimal(10,2) DEFAULT NULL,
                    `last_hash` varchar(64) DEFAULT NULL,
                    `last_notified_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unicity` (`plugin_credit_entities_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;
SQL;
            $DB->doQuery($query);
        }

        // Drop legacy cache table if present (no longer used)
        $migration->dropTable('glpi_plugin_creditalert_cache');

        PluginCreditalertConfig::refreshViews();

        if (class_exists(PluginCreditalertProfile::class)) {
            PluginCreditalertProfile::install($migration);
        }

        if (class_exists('CronTask')) {
            if ($DB->tableExists('glpi_crontasks')) {
                $DB->delete('glpi_crontasks', [
                    'itemtype' => PluginCreditalertAlertTask::class,
                    'name'     => 'cronCreditalert',
                ]);
            }
            CronTask::register(
                PluginCreditalertAlertTask::class,
                'creditalert',
                HOUR_TIMESTAMP,
                [
                    'comment' => __('Scan credits and notify when thresholds are reached', 'creditalert'),
                    'mode'    => CronTask::MODE_EXTERNAL,
                ]
            );
        }

        $migration->executeMigration();

        return true;
    }

    /**
     * Remove plugin database schema.
     *
     * @return bool
     */
    public static function uninstall(): bool
    {
        global $DB;

        foreach (glob(__DIR__ . '/../inc/*.class.php') as $filepath) {
            if (preg_match("/inc.(.+)\.class.php/", $filepath) !== 0) {
                include_once $filepath;
            }
        }

        $migration = new Migration(PLUGIN_CREDITALERT_VERSION);
        $migration->dropTable('glpi_plugin_creditalert_cache');
        $migration->dropTable('glpi_plugin_creditalert_notifications');
        $migration->dropTable('glpi_plugin_creditalert_entityconfigs');
        $migration->dropTable('glpi_plugin_creditalert_configs');
        $DB->doQuery("DROP VIEW IF EXISTS `glpi_plugin_creditalert_vcredits`;");
        $DB->doQuery("DROP VIEW IF EXISTS `glpi_plugin_creditalert_vconsumptions`;");
        $migration->executeMigration();

        if (class_exists(PluginCreditalertProfile::class)) {
            PluginCreditalertProfile::removeRights();
        }

        if ($DB->tableExists('glpi_crontasks')) {
            $DB->delete(
                'glpi_crontasks',
                ['itemtype' => PluginCreditalertAlertTask::class]
            );
        }

        return true;
    }
}
