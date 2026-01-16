<?php

include('../../../inc/includes.php');

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

PluginCreditalertConfig::ensureViews();

$ids = $_SESSION['plugin_creditalert']['export_consumptions'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}
$ids = array_values(array_filter(array_map('intval', $ids)));
unset($_SESSION['plugin_creditalert']['export_consumptions']);

if (empty($ids)) {
    Html::displayErrorAndDie(__('Aucun element selectionne.', 'creditalert'));
}

/** @var DBmysql $DB */
global $DB;

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
        "$view.id" => $ids,
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
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if ($text === '') {
            continue;
        }
        $tasksByTicket[(int) $task['tickets_id']][] = $text;
    }
}

$filename = 'creditalert_consumptions_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, [
    __('N° Ticket', 'creditalert'),
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
    $entityName = PluginCreditalertConfig::getEntityShortName((int) ($row['entities_id'] ?? 0));
    $categoryName = Dropdown::getDropdownName('glpi_itilcategories', (int) ($row['itilcategories_id'] ?? 0));
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
        $row['ticket_title'] ?? '',
        $tasksText,
        $row['consumed'] ?? '',
        $row['credit_label'] ?? '',
    ], ';');
}

fclose($out);
exit;
