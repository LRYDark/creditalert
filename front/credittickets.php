<?php

include('../../../inc/includes.php');

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

$credit_id = (int) ($_GET['credit_id'] ?? 0);
if ($credit_id <= 0) {
    Html::displayErrorAndDie(__('Credit manquant', 'creditalert'));
}

$config = PluginCreditalertConfig::getConfig();

$creditTable = $config['credit_table'];
$consumptionTable = $config['consumption_table'];
$fieldTicket = $config['field_ticket'];
$fieldFk = $config['field_fk_usage'];
$fieldUsed = $config['field_used'];

/** @var DBmysql $DB */
global $DB, $CFG_GLPI;

$tickets = [];
foreach ($DB->request([
    'SELECT' => [
        'glpi_tickets.id AS id',
        'glpi_tickets.name AS name',
        'glpi_tickets.status AS status',
        'glpi_tickets.date AS date',
        "$consumptionTable.$fieldUsed AS consumed",
        "$consumptionTable.date_creation AS consume_date",
    ],
    'FROM'   => $consumptionTable,
    'LEFT JOIN' => [
        'glpi_tickets' => [
            'ON' => [
                'glpi_tickets' => 'id',
                $consumptionTable => $fieldTicket,
            ],
        ],
    ],
    'WHERE'  => [
        $consumptionTable . '.' . $fieldFk => $credit_id,
    ],
]) as $row) {
    $tickets[] = $row;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    PluginCreditalertConfig::ensureViews();

    $view = 'glpi_plugin_creditalert_vconsumptions';

    $rows = [];
    $ticketIds = [];
    foreach ($DB->request([
        'SELECT' => [
            "$view.id AS consumption_id",
            "$view.entities_id AS entities_id",
            "$view.credit_label AS credit_label",
            "$view.consumed AS consumed",
            "$view.consume_date AS consume_date",
            "$view.ticket_id AS ticket_id",
            'glpi_tickets.type AS ticket_type',
            'glpi_tickets.status AS ticket_status',
            'glpi_tickets.date AS ticket_open_date',
            'glpi_tickets.solvedate AS ticket_solvedate',
            'glpi_tickets.closedate AS ticket_closedate',
            'glpi_tickets.takeintoaccountdate AS ticket_take_date',
            'glpi_tickets.takeintoaccount_delay_stat AS ticket_take_delay',
            'glpi_tickets.waiting_duration AS ticket_waiting_duration',
            'glpi_tickets.actiontime AS ticket_actiontime',
            'glpi_tickets.itilcategories_id AS itilcategories_id',
            'glpi_tickets.name AS ticket_title',
        ],
        'FROM' => $view,
        'LEFT JOIN' => [
            'glpi_tickets' => [
                'ON' => [
                    $view => 'ticket_id',
                    'glpi_tickets' => 'id',
                ],
            ],
        ],
        'WHERE' => [
            "$view.credit_id" => $credit_id,
        ],
        'ORDER' => [
            "$view.id",
        ],
    ]) as $row) {
        $rows[] = $row;
        if (!empty($row['ticket_id'])) {
            $ticketIds[(int) $row['ticket_id']] = true;
        }
    }

    if (empty($rows)) {
        Html::displayErrorAndDie(__('Aucun element selectionne.', 'creditalert'));
    }

    $tasksByTicket = [];
    if (!empty($ticketIds)) {
        $taskWhere = [
            'tickets_id' => array_keys($ticketIds),
        ];
        if ($DB->fieldExists('glpi_tickettasks', 'is_deleted')) {
            $taskWhere['is_deleted'] = 0;
        }
        foreach ($DB->request([
            'SELECT' => [
                'tickets_id',
                'content',
            ],
            'FROM'  => 'glpi_tickettasks',
            'WHERE' => $taskWhere,
            'ORDER' => [
                'tickets_id',
                'id',
            ],
        ]) as $task) {
        $text = html_entity_decode((string) ($task['content'] ?? ''), ENT_QUOTES, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
            if ($text === '') {
                continue;
            }
            $tasksByTicket[(int) $task['tickets_id']][] = $text;
        }
    }

    $entityIdForExport = (int) ($rows[0]['entities_id'] ?? 0);
    $filename = PluginCreditalertConfig::getExportFilename($entityIdForExport);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    $normalize = static function ($value): string {
        $text = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        return trim($text);
    };
    fputcsv($out, [
        __("N\u{00B0} Ticket", 'creditalert'),
        __('Entite', 'creditalert'),
        __('Type', 'creditalert'),
        __('Statut du ticket', 'creditalert'),
        __('Date d\'ouverture', 'creditalert'),
        __('Temps de prise en compte', 'creditalert'),
        __('Date de resolution', 'creditalert'),
        __('Annee', 'creditalert'),
        __('Temps Minutes', 'creditalert'),
        __('Temps en attente', 'creditalert'),
        __('Temps de resolution', 'creditalert'),
        __('Categorie', 'creditalert'),
        __('Titre', 'creditalert'),
        __('Taches - Description', 'creditalert'),
        __('Credit consomme', 'creditalert'),
        __('Credit associe au ticket', 'creditalert'),
    ], ';');

    foreach ($rows as $row) {
        $ticketId = (int) ($row['ticket_id'] ?? 0);
        $entityName = $normalize(PluginCreditalertConfig::getEntityShortName((int) ($row['entities_id'] ?? 0)));
        $categoryName = $normalize(Dropdown::getDropdownName('glpi_itilcategories', (int) ($row['itilcategories_id'] ?? 0)));
        $ticketType = Ticket::getTicketTypeName((int) ($row['ticket_type'] ?? 0));
        $ticketStatus = Ticket::getStatus((int) ($row['ticket_status'] ?? 0));

        $openDate = $row['ticket_open_date'] ?? '';
        $resolvedDate = $row['ticket_solvedate'] ?: ($row['ticket_closedate'] ?? '');

        $yearSource = $row['consume_date'] ?: $openDate;
        $year = '';
        if (!empty($yearSource)) {
            $ts = strtotime((string) $yearSource);
            if ($ts !== false) {
                $year = date('Y', $ts);
            }
        }

        $takeIntoAccountMinutes = '';
        if (isset($row['ticket_take_delay']) && $row['ticket_take_delay'] !== null) {
            $takeIntoAccountMinutes = (int) round(((int) $row['ticket_take_delay']) / 60);
        } elseif (!empty($row['ticket_take_date']) && !empty($openDate)) {
            $openTs = strtotime((string) $openDate);
            $takeTs = strtotime((string) $row['ticket_take_date']);
            if ($openTs !== false && $takeTs !== false) {
                $takeIntoAccountMinutes = (int) round(($takeTs - $openTs) / 60);
            }
        }

        $actiontimeMinutes = '';
        if (isset($row['ticket_actiontime']) && $row['ticket_actiontime'] !== null) {
            $actiontimeMinutes = (int) round(((int) $row['ticket_actiontime']) / 60);
        }

        $waitingMinutes = '';
        if (isset($row['ticket_waiting_duration']) && $row['ticket_waiting_duration'] !== null) {
            $waitingMinutes = (int) round(((int) $row['ticket_waiting_duration']) / 60);
        }

        $resolutionMinutes = '';
        if (!empty($openDate) && !empty($resolvedDate)) {
            $startTs = strtotime((string) $openDate);
            $endTs = strtotime((string) $resolvedDate);
            if ($startTs !== false && $endTs !== false) {
                $totalMinutes = (int) round(($endTs - $startTs) / 60);
                $waiting = $waitingMinutes !== '' ? (int) $waitingMinutes : 0;
                $resolutionMinutes = max(0, $totalMinutes - $waiting);
            }
        }

        $tasks = $tasksByTicket[$ticketId] ?? [];
        $tasksText = implode(' | ', $tasks);

        fputcsv($out, [
            $ticketId,
            $entityName,
            $ticketType,
            $ticketStatus,
            $openDate,
            $takeIntoAccountMinutes,
            $resolvedDate,
            $year,
            $actiontimeMinutes,
            $waitingMinutes,
            $resolutionMinutes,
            $categoryName,
            $normalize($row['ticket_title'] ?? ''),
            $tasksText,
            $row['consumed'] ?? '',
            $normalize($row['credit_label'] ?? ''),
        ], ';');
    }

    fclose($out);
    exit;
}

Html::header(
    __('Tickets consommant ce credit', 'creditalert'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'creditalert',
    'creditlist'
);

echo "<div class='card'><div class='card-body'>";
echo "<div class='d-flex justify-content-end mb-2'>";
$exportUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/credittickets.php?credit_id=' . $credit_id . '&export=csv';
echo "<a class='btn btn-secondary' href='$exportUrl'>" . __('Export CSV') . "</a>";
echo "</div>";

echo "<div class='table-responsive'>";
echo "<table class='table table-hover'>";
echo "<thead><tr>";
echo "<th>" . __('Ticket') . "</th>";
echo "<th>" . __('Statut') . "</th>";
echo "<th>" . __('Date du ticket') . "</th>";
echo "<th>" . __('Date de consommation', 'creditalert') . "</th>";
echo "<th class='text-end'>" . __('Quantite consommee', 'creditalert') . "</th>";
echo "</tr></thead><tbody>";

foreach ($tickets as $t) {
    $url = Ticket::getFormURLWithID((int) $t['id']);
    echo "<tr>";
    echo "<td><a href='{$url}'>" . Html::entities_deep($t['name']) . "</a></td>";
    echo "<td>" . Ticket::getStatus($t['status']) . "</td>";
    echo "<td>" . Html::convDateTime($t['date']) . "</td>";
    echo "<td>" . Html::convDateTime($t['consume_date']) . "</td>";
    echo "<td class='text-end'>" . (float) $t['consumed'] . "</td>";
    echo "</tr>";
}

echo "</tbody></table>";
echo "</div></div></div>";

Html::footer();
