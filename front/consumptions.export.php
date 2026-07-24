<?php

include('../../../inc/includes.php');

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

PluginCreditalertConfig::ensureViews();

$ids = $_SESSION['plugin_creditalert']['export_consumptions'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}
$ids = array_values(array_filter(array_map('intval', $ids)));
unset($_SESSION['plugin_creditalert']['export_consumptions']);

$includePrivateTasks = (int) ($_SESSION['plugin_creditalert']['export_include_private'] ?? 0) === 1;
unset($_SESSION['plugin_creditalert']['export_include_private']);

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
    if (!$includePrivateTasks) {
        $taskWhere['is_private'] = 0;
    }
    foreach ($DB->request([
        'SELECT' => [
            'tickets_id',
            'content',
            'is_private',
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
        $tasksByTicket[(int) $task['tickets_id']][] = [
            'text'       => $text,
            'is_private' => (int) ($task['is_private'] ?? 0) === 1,
        ];
    }
}

$normalize = static function ($value): string {
    $text = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    return trim($text);
};
$getCategoryLabel = static function (int $categoryId) use ($normalize): string {
    static $cache = [];
    if ($categoryId <= 0) {
        return '';
    }
    if (!array_key_exists($categoryId, $cache)) {
        $cache[$categoryId] = $normalize(Dropdown::getDropdownName('glpi_itilcategories', $categoryId));
    }
    return (string) $cache[$categoryId];
};
$getEntityShortName = static function (int $entityId) use ($normalize): string {
    static $cache = [];
    if ($entityId <= 0) {
        return '';
    }
    if (!array_key_exists($entityId, $cache)) {
        $cache[$entityId] = $normalize(PluginCreditalertConfig::getEntityShortName($entityId));
    }
    return (string) $cache[$entityId];
};

$categoryPartsByIndex = [];
$maxCategoryParts = 1;
foreach ($rows as $index => $row) {
    $categoryLabel = $getCategoryLabel((int) ($row['itilcategories_id'] ?? 0));
    $parts = array_values(array_filter(
        array_map('trim', explode('>', $categoryLabel)),
        static function ($part) {
            return $part !== '';
        }
    ));
    if (empty($parts) && $categoryLabel !== '') {
        $parts = [$categoryLabel];
    }
    $categoryPartsByIndex[$index] = $parts;
    $maxCategoryParts = max($maxCategoryParts, count($parts));
}

$entityIdForExport = (int) ($rows[0]['entities_id'] ?? 0);
$filename = preg_replace('/\.csv$/', '', PluginCreditalertConfig::getExportFilename($entityIdForExport)) . '.xlsx';

$categoryHeaders = [__('Categorie', 'creditalert')];
for ($i = 1; $i < $maxCategoryParts; $i++) {
    $categoryHeaders[] = sprintf(__('Sous categorie %d', 'creditalert'), $i);
}
$headers = [
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
];
$headers = array_merge($headers, $categoryHeaders, [
    __('Titre', 'creditalert'),
    __('Taches - Description', 'creditalert'),
    __('Credit consomme', 'creditalert'),
    __('Credit associe au ticket', 'creditalert'),
]);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr(__('Consommations', 'creditalert'), 0, 31));

$columnCount = count($headers);
$tasksColumnIndex = 11 + $maxCategoryParts + 2; // after fixed columns + categories + title
$lastColumnLetter = Coordinate::stringFromColumnIndex($columnCount);
$tasksColumnLetter = Coordinate::stringFromColumnIndex($tasksColumnIndex);

foreach ($headers as $col => $header) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . '1', $header);
}
$sheet->getStyle('A1:' . $lastColumnLetter . '1')->getFont()->setBold(true);
$sheet->getStyle('A1:' . $lastColumnLetter . '1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->freezePane('A2');

$rowNumber = 2;
foreach ($rows as $index => $row) {
    $ticketId = (int) ($row['ticket_id'] ?? 0);
    $entityName = $getEntityShortName((int) ($row['entities_id'] ?? 0));
    $categoryParts = $categoryPartsByIndex[$index] ?? [];
    $categoryCells = [];
    for ($i = 0; $i < $maxCategoryParts; $i++) {
        $categoryCells[] = $categoryParts[$i] ?? '';
    }
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

    $rowValues = [
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
    ];
    $rowValues = array_merge($rowValues, $categoryCells, [
        $normalize($row['ticket_title'] ?? ''),
        null, // tasks cell handled below as rich text
        ($row['consumed'] ?? '') === '' ? '' : (float) $row['consumed'],
        $normalize($row['credit_label'] ?? ''),
    ]);

    foreach ($rowValues as $col => $value) {
        if ($col + 1 === $tasksColumnIndex) {
            continue;
        }
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $rowNumber, $value);
    }

    // Tasks cell: "TACHE n :" in bold, task text, blank line between tasks
    $tasks = $tasksByTicket[$ticketId] ?? [];
    if (!empty($tasks)) {
        $richText = new RichText();
        foreach ($tasks as $i => $task) {
            if ($i > 0) {
                $richText->createText("\n\n");
            }
            $labelText = sprintf(__('TACHE %d', 'creditalert'), $i + 1)
                . ($task['is_private'] ? ' 🔒' : '')
                . ' : ';
            $label = $richText->createTextRun($labelText);
            $label->getFont()->setBold(true);
            $richText->createText($task['text']);
        }
        $sheet->setCellValue($tasksColumnLetter . $rowNumber, $richText);
    }

    $rowNumber++;
}

if ($rowNumber > 2) {
    $dataRange = 'A2:' . $lastColumnLetter . ($rowNumber - 1);
    $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $sheet->getStyle($tasksColumnLetter . '2:' . $tasksColumnLetter . ($rowNumber - 1))
        ->getAlignment()->setWrapText(true);
}

for ($col = 1; $col <= $columnCount; $col++) {
    $letter = Coordinate::stringFromColumnIndex($col);
    if ($col === $tasksColumnIndex) {
        $sheet->getColumnDimension($letter)->setWidth(80);
    } else {
        $sheet->getColumnDimension($letter)->setAutoSize(true);
    }
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
