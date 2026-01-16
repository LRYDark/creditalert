<?php

class PluginCreditalertConfig extends CommonDBTM
{
    public static $rightname = PluginCreditalertProfile::RIGHTNAME;
    private static $_instance = null;

    public static function getTypeName($nb = 0)
    {
        return _n('Configuration des alertes de credit', 'Configurations des alertes de credit', $nb, 'creditalert');
    }

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_creditalert_configs';
    }

    public static function getDefaultConfig(): array
    {
        return [
            'id'                 => 1,
            'alert_threshold'    => 80,
            'notification_emails'=> '',
            'credit_table'       => 'glpi_plugin_credit_entities',
            'consumption_table'  => 'glpi_plugin_credit_tickets',
            'field_sold'         => 'quantity',
            'field_used'         => 'consumed',
            'field_entity'       => 'entities_id',
            'field_client'       => 'name',
            'field_fk_usage'     => 'plugin_credit_entities_id',
            'field_end_date'     => 'end_date',
            'field_is_active'    => 'is_active',
            'field_ticket'       => 'tickets_id',
            'color_warning'      => '#f7c77d',
            'color_over'         => '#f2958a',
        ];
    }

    public static function getConfig(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $request = [
            'SELECT' => '*',
            'FROM'   => self::getTable(),
            'LIMIT'  => 1,
        ];
        $config = $DB->request($request)->current();
        if (!$config) {
            $config = self::getDefaultConfig();
        }

        return $config;
    }

    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
            if (!self::$_instance->getFromDB(1)) {
                self::$_instance->getEmpty();
            }
        }
        return self::$_instance;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'Config') {
            return self::createTabEntry(self::getTypeName(2));
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() == 'Config') {
            if (Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG)) {
                self::showConfigPage();
            } else {
                Html::displayRightError();
            }
        }
        return true;
    }

    public static function showConfigPage(): void
    {
        $GLOBALS['PLUGIN_CREDITALERT_FROM_TAB'] = true;
        include __DIR__ . '/../front/config.form.php';
        unset($GLOBALS['PLUGIN_CREDITALERT_FROM_TAB']);
    }

    public static function sanitizeEmails(string $raw): string
    {
        $addresses = array_filter(array_map('trim', explode(',', $raw)));
        $addresses = array_unique($addresses);
        return implode(',', $addresses);
    }

    public static function getRecipientsForEntity(int $entities_id): array
    {
        $entityConfig = PluginCreditalertEntityConfig::getForEntity($entities_id);
        $emails = $entityConfig['notification_emails'] ?? '';
        if (empty($emails)) {
            $global = self::getConfig();
            $emails = $global['notification_emails'] ?? '';
        }

        $clean = self::sanitizeEmails((string) $emails);
        return $clean === '' ? [] : explode(',', $clean);
    }

    public static function getThresholdForEntity(int $entities_id): int
    {
        $entityConfig = PluginCreditalertEntityConfig::getForEntity($entities_id);
        if (isset($entityConfig['alert_threshold']) && $entityConfig['alert_threshold'] !== '') {
            return (int) $entityConfig['alert_threshold'];
        }

        $config = self::getConfig();
        return (int) ($config['alert_threshold'] ?? 0);
    }

    public static function getEntityShortName(int $entityId): string
    {
        $name = Dropdown::getDropdownName('glpi_entities', $entityId);
        if ($name === '') {
            return '';
        }
        if (strpos($name, '>') === false) {
            return $name;
        }
        $parts = array_map('trim', explode('>', $name));
        $parts = array_values(array_filter($parts, static function ($part) {
            return $part !== '';
        }));
        if (empty($parts)) {
            return $name;
        }
        return (string) end($parts);
    }

    public static function updateConfig(array $input): bool
    {
        $config = self::getConfig();
        $cfgItem = new self();
        $input['id'] = $config['id'];

        $input['notification_emails'] = self::sanitizeEmails($input['notification_emails'] ?? '');
        $input['color_warning'] = self::sanitizeColor($input['color_warning'] ?? '#f7c77d');
        $input['color_over'] = self::sanitizeColor($input['color_over'] ?? '#f2958a');
        $input['credit_table'] = self::sanitizeIdentifier($input['credit_table'] ?? $config['credit_table'], $config['credit_table']);
        $input['consumption_table'] = self::sanitizeIdentifier($input['consumption_table'] ?? $config['consumption_table'], $config['consumption_table']);
        $input['field_sold'] = self::sanitizeIdentifier($input['field_sold'] ?? $config['field_sold'], $config['field_sold']);
        $input['field_used'] = self::sanitizeIdentifier($input['field_used'] ?? $config['field_used'], $config['field_used']);
        $input['field_entity'] = self::sanitizeIdentifier($input['field_entity'] ?? $config['field_entity'], $config['field_entity']);
        $input['field_client'] = self::sanitizeIdentifier($input['field_client'] ?? $config['field_client'], $config['field_client']);
        $input['field_fk_usage'] = self::sanitizeIdentifier($input['field_fk_usage'] ?? $config['field_fk_usage'], $config['field_fk_usage']);
        $input['field_end_date'] = self::sanitizeIdentifier($input['field_end_date'] ?? $config['field_end_date'], $config['field_end_date'], true);
        $input['field_is_active'] = self::sanitizeIdentifier($input['field_is_active'] ?? $config['field_is_active'], $config['field_is_active'], true);
        $input['field_ticket'] = self::sanitizeIdentifier($input['field_ticket'] ?? $config['field_ticket'], $config['field_ticket'], true);

        if ($cfgItem->getFromDB($input['id'])) {
            $updated = (bool) $cfgItem->update($input);
            if ($updated) {
                self::refreshViews();
            }
            return $updated;
        }

        $added = (bool) $cfgItem->add($input + self::getDefaultConfig());
        if ($added) {
            self::refreshViews();
        }
        return $added;
    }

    private static function viewExists(string $name): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        $row = $DB->request([
            'COUNT' => 'cpt',
            'FROM'   => 'information_schema.tables',
            'WHERE'  => [
                'table_schema' => $DB->dbdefault,
                'table_name'   => $name,
            ],
        ])->current();

        return (int) ($row['cpt'] ?? 0) > 0;
    }

    private static function registerViewsInDbCache(array $views): void
    {
        /** @var DBmysql $DB */
        global $DB;

        try {
            $ref = new ReflectionClass($DB);
            if (!$ref->hasProperty('table_cache')) {
                return;
            }
            $prop = $ref->getProperty('table_cache');
            $prop->setAccessible(true);
            $current = (array) $prop->getValue($DB);
            $prop->setValue($DB, array_values(array_unique(array_merge($current, $views))));
        } catch (ReflectionException $e) {
            // Ignore cache update if reflection fails.
        }
    }

    public static function ensureViews(): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $creditsView = 'glpi_plugin_creditalert_vcredits';
        $consumptionsView = 'glpi_plugin_creditalert_vconsumptions';

        $needsRefresh = false;
        if (!self::viewExists($creditsView) || !self::viewExists($consumptionsView)) {
            $needsRefresh = true;
        } else {
            $columns = [];
            foreach ($DB->request([
                'SELECT' => ['column_name'],
                'FROM'   => 'information_schema.columns',
                'WHERE'  => [
                    'table_schema' => $DB->dbdefault,
                    'table_name'   => $consumptionsView,
                ],
            ]) as $row) {
                $columns[$row['column_name']] = true;
            }
            if (!isset($columns['has_consumption']) || !isset($columns['ticket_end_date'])) {
                $needsRefresh = true;
            }
        }

        if ($needsRefresh) {
            self::refreshViews();
        }

        self::registerViewsInDbCache([$creditsView, $consumptionsView]);
    }

    public static function refreshViews(?array $config = null): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $defaults = self::getDefaultConfig();
        if ($config === null) {
            $config = self::getConfig();
        }

        $creditTable = self::sanitizeIdentifier($config['credit_table'] ?? $defaults['credit_table'], $defaults['credit_table']);
        $consumptionTable = self::sanitizeIdentifier($config['consumption_table'] ?? $defaults['consumption_table'], $defaults['consumption_table']);
        $fieldSold = self::sanitizeIdentifier($config['field_sold'] ?? $defaults['field_sold'], $defaults['field_sold']);
        $fieldUsed = self::sanitizeIdentifier($config['field_used'] ?? $defaults['field_used'], $defaults['field_used']);
        $fieldEntity = self::sanitizeIdentifier($config['field_entity'] ?? $defaults['field_entity'], $defaults['field_entity']);
        $fieldClient = self::sanitizeIdentifier($config['field_client'] ?? $defaults['field_client'], $defaults['field_client']);
        $fieldFkUsage = self::sanitizeIdentifier($config['field_fk_usage'] ?? $defaults['field_fk_usage'], $defaults['field_fk_usage']);
        $fieldEndDate = self::sanitizeIdentifier($config['field_end_date'] ?? $defaults['field_end_date'], $defaults['field_end_date'], true);
        $fieldTicket = self::sanitizeIdentifier($config['field_ticket'] ?? $defaults['field_ticket'], $defaults['field_ticket'], true);
        if ($fieldTicket === '') {
            $fieldTicket = $defaults['field_ticket'];
        }

        $endDateExpr = $fieldEndDate !== '' ? "c.`{$fieldEndDate}`" : 'NULL';
        $quantitySoldExpr = "c.`{$fieldSold}`";
        $quantityUsedExpr = "COALESCE(SUM(ct.`{$fieldUsed}`), 0)";
        $percentageExpr = "CASE WHEN {$quantitySoldExpr} > 0 THEN ROUND(({$quantityUsedExpr} / {$quantitySoldExpr}) * 100, 2) ELSE 0 END";
        $thresholdExpr = "COALESCE(ec.alert_threshold, cfg.alert_threshold)";

        $viewCredits = 'glpi_plugin_creditalert_vcredits';
        $DB->doQuery("DROP VIEW IF EXISTS `{$viewCredits}`;");
        $DB->doQuery(
            "
            CREATE VIEW `{$viewCredits}` AS
            SELECT
                c.id AS id,
                c.`{$fieldEntity}` AS entities_id,
                c.`{$fieldClient}` AS client_label,
                {$quantitySoldExpr} AS quantity_sold,
                {$quantityUsedExpr} AS quantity_used,
                {$percentageExpr} AS percentage_used,
                {$thresholdExpr} AS applied_threshold,
                CASE
                    WHEN {$endDateExpr} IS NOT NULL AND {$endDateExpr} < NOW() THEN 'EXPIRED'
                    WHEN {$quantitySoldExpr} > 0 AND {$quantityUsedExpr} > {$quantitySoldExpr} THEN 'OVER'
                    WHEN {$quantitySoldExpr} > 0 AND {$percentageExpr} >= {$thresholdExpr} THEN 'WARNING'
                    ELSE 'OK'
                END AS status,
                {$endDateExpr} AS end_date,
                MAX(ct.`{$fieldTicket}`) AS last_ticket_id
            FROM `{$creditTable}` c
            LEFT JOIN `{$consumptionTable}` ct
                ON ct.`{$fieldFkUsage}` = c.id
            LEFT JOIN glpi_plugin_creditalert_entityconfigs ec ON ec.entities_id = c.`{$fieldEntity}`
            CROSS JOIN (SELECT alert_threshold FROM glpi_plugin_creditalert_configs ORDER BY id ASC LIMIT 1) cfg
            GROUP BY c.id, c.`{$fieldEntity}`, c.`{$fieldClient}`, c.`{$fieldSold}`, {$endDateExpr}, ec.alert_threshold, cfg.alert_threshold;
            "
        );

        $viewConsumptions = 'glpi_plugin_creditalert_vconsumptions';
        $DB->doQuery("DROP VIEW IF EXISTS `{$viewConsumptions}`;");
        $DB->doQuery(
            "
            CREATE VIEW `{$viewConsumptions}` AS
            SELECT
                ct.id AS id,
                ct.`{$fieldFkUsage}` AS credit_id,
                c.`{$fieldEntity}` AS entities_id,
                c.`{$fieldClient}` AS credit_label,
                ct.`{$fieldUsed}` AS consumed,
                ct.date_creation AS consume_date,
                ct.`{$fieldTicket}` AS ticket_id,
                t.name AS ticket_name,
                t.status AS ticket_status,
                t.date AS ticket_date,
                t.solvedate AS ticket_solvedate,
                t.closedate AS ticket_closedate,
                COALESCE(t.solvedate, t.closedate) AS ticket_end_date,
                1 AS has_consumption
            FROM `{$consumptionTable}` ct
            LEFT JOIN `{$creditTable}` c ON c.id = ct.`{$fieldFkUsage}`
            LEFT JOIN `glpi_tickets` t ON t.id = ct.`{$fieldTicket}`
            UNION ALL
            SELECT
                -t.id AS id,
                NULL AS credit_id,
                t.entities_id AS entities_id,
                NULL AS credit_label,
                0 AS consumed,
                NULL AS consume_date,
                t.id AS ticket_id,
                t.name AS ticket_name,
                t.status AS ticket_status,
                t.date AS ticket_date,
                t.solvedate AS ticket_solvedate,
                t.closedate AS ticket_closedate,
                COALESCE(t.solvedate, t.closedate) AS ticket_end_date,
                0 AS has_consumption
            FROM `glpi_tickets` t
            WHERE NOT EXISTS (
                SELECT 1
                FROM `{$consumptionTable}` ct2
                WHERE ct2.`{$fieldTicket}` = t.id
            );
            "
        );
    }

    public static function sanitizeColor(string $color): string
    {
        $color = trim($color);
        if ($color === '') {
            return '#000000';
        }
        if ($color[0] !== '#') {
            $color = '#' . $color;
        }
        return substr($color, 0, 7);
    }

    public static function sanitizeIdentifier(string $value, string $fallback, bool $allowEmpty = false): string
    {
        $value = trim($value);
        if ($value === '') {
            return $allowEmpty ? '' : $fallback;
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            return $fallback;
        }
        return $value;
    }
}

class PluginCreditalertEntityConfig extends CommonDBTM
{
    public static $rightname = PluginCreditalertProfile::RIGHTNAME;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_creditalert_entityconfigs';
    }

    public static function getForEntity(int $entity_id): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'SELECT' => '*',
            'FROM'   => self::getTable(),
            'WHERE'  => ['entities_id' => $entity_id],
            'LIMIT'  => 1,
        ]);

        $data = $iterator->current();
        return $data ? $data : [];
    }

    public static function upsert(array $input): bool
    {
        $item = new self();
        $existing = self::getForEntity((int) $input['entities_id']);

        $input['notification_emails'] = PluginCreditalertConfig::sanitizeEmails($input['notification_emails'] ?? '');

        if ($existing) {
            $input['id'] = $existing['id'];
            return (bool) $item->update($input);
        }

        return (bool) $item->add($input);
    }

    public static function deleteForEntity(int $entity_id): void
    {
        $item = new self();
        $existing = self::getForEntity($entity_id);
        if ($existing) {
            $item->delete(['id' => $existing['id']]);
        }
    }

    public static function getAllConfigs(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $rows = [];
        foreach ($DB->request(['FROM' => self::getTable()]) as $data) {
            $rows[] = $data;
        }

        return $rows;
    }
}
