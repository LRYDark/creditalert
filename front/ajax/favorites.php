<?php

include('../../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

header('Content-Type: application/json; charset=utf-8');

/** @var DBmysql $DB */
global $DB;

$action = (string) ($_POST['action'] ?? ($_GET['action'] ?? ''));
$userId = (int) Session::getLoginUserID();
$table  = 'glpi_plugin_creditalert_favorites';

try {
    // Create table on-the-fly if missing (migration not yet run)
    if (!$DB->tableExists($table)) {
        $charset   = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $keySign   = DBConnection::getDefaultPrimaryKeySignOption();
        $DB->doQuery("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int {$keySign} NOT NULL auto_increment,
            `users_id` int {$keySign} NOT NULL DEFAULT '0',
            `name` varchar(255) NOT NULL DEFAULT '',
            `ca_names` text,
            `ca_status` varchar(20) NOT NULL DEFAULT 'all',
            `ca_show_over` tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // Add ca_show_over column if table exists but column is missing
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'ca_show_over')) {
        $DB->doQuery("ALTER TABLE `{$table}` ADD COLUMN `ca_show_over` tinyint NOT NULL DEFAULT '1' AFTER `ca_status`");
    }

    switch ($action) {

        case 'list':
            $rows = [];
            foreach ($DB->request([
                'SELECT' => ['id', 'name', 'ca_names', 'ca_status', 'ca_show_over'],
                'FROM'   => $table,
                'WHERE'  => ['users_id' => $userId],
                'ORDER'  => ['name'],
            ]) as $row) {
                $caNames = [];
                if (!empty($row['ca_names'])) {
                    $decoded = json_decode((string) $row['ca_names'], true);
                    if (is_array($decoded)) {
                        $caNames = $decoded;
                    }
                }
                $rows[] = [
                    'id'           => (int) $row['id'],
                    'name'         => (string) $row['name'],
                    'ca_names'     => $caNames,
                    'ca_status'    => (string) $row['ca_status'],
                    'ca_show_over' => (int) ($row['ca_show_over'] ?? 1),
                ];
            }
            echo json_encode($rows);
            break;

        case 'save':
            $name     = trim((string) ($_POST['name'] ?? ''));
            $caNames  = array_values(array_filter((array) ($_POST['ca_names'] ?? [])));
            $caStatus = in_array($_POST['ca_status'] ?? 'all', ['all', 'active', 'inactive'], true)
                        ? (string) ($_POST['ca_status'] ?? 'all') : 'all';
            $caShowOver = (($_POST['ca_show_over'] ?? '1') === '0') ? 0 : 1;

            if ($name === '') {
                http_response_code(400);
                echo json_encode(['error' => 'name required']);
                break;
            }

            $DB->insert($table, [
                'users_id'     => $userId,
                'name'         => $name,
                'ca_names'     => json_encode($caNames),
                'ca_status'    => $caStatus,
                'ca_show_over' => $caShowOver,
            ]);

            echo json_encode(['id' => (int) $DB->insertId(), 'name' => $name]);
            break;

        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $DB->delete($table, ['id' => $id, 'users_id' => $userId]);
            }
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'unknown action: ' . htmlspecialchars($action)]);
            break;
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file'  => basename($e->getFile()),
        'line'  => $e->getLine(),
    ]);
}
