<?php

/** @var array $CFG_GLPI */
global $CFG_GLPI;

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

// Tabs: consumptions (view), credits summary (native search) and credit alerts (view)
$view = $_GET['view'] ?? ($_POST['view'] ?? 'consumptions');
if (!in_array($view, ['consumptions', 'credits', 'creditalert'], true)) {
    $view = 'consumptions';
}

// Recherche par défaut (étoile du panneau des recherches sauvegardées) : à l'arrivée sur l'onglet, sans filtre.
// Avant Html::header() : c'est une redirection.
if (empty($_POST) && array_diff(array_keys($_GET), ['view']) === []) {
    if ($view === 'consumptions') {
        PluginCreditalertSavedsearch::loadDefault(PluginCreditalertConsumption::class);
    } elseif ($view === 'creditalert') {
        PluginCreditalertSavedsearch::loadDefault(PluginCreditalertCreditItem::class);
    }
}

Html::header(
    PluginCreditalertCreditItem::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'plugins',
    'creditalert',
    'creditlist'
);

$esc = static function ($text): string {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
};
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$json = static function ($value) use ($jsonFlags): string {
    return (string) json_encode($value, $jsonFlags);
};
$decode = static function ($text): string {
    return html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
};
$getString = static function (string $key): string {
    $value = $_GET[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
};
$normalizeIntList = static function ($values): array {
    if (!is_array($values)) {
        $values = [$values];
    }
    $values = array_filter($values, 'is_scalar');
    return array_values(array_unique(array_filter(array_map('intval', $values))));
};
// Petit « i » d'aide avec bulle GLPI
$help = static function (string $text) use ($esc): string {
    return " <i class='ti ti-info-circle text-muted creditalert-help' data-bs-toggle='tooltip' data-bs-placement='top'"
        . " title='" . $esc($text) . "' aria-label='" . $esc($text) . "'></i>";
};

echo "<div class='card mb-3'><div class='card-body'>";
echo "<ul class='nav nav-tabs' id='creditalert-tabs' role='tablist'>";
$tabs = [
    'consumptions' => __('Consommations par client', 'creditalert'),
    'credits'      => __('Synthese des credits', 'creditalert'),
    'creditalert'  => __('Alertes de crédit', 'creditalert'),
];
foreach ($tabs as $tabKey => $label) {
    $active = $view === $tabKey ? 'active' : '';
    $url = $esc($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php?view=' . $tabKey);
    echo "<li class='nav-item' role='presentation'>";
    echo "<a class='nav-link $active' href='$url' data-creditalert-tab>" . $esc($label) . "</a>";
    echo "</li>";
}
echo "</ul>";
echo "</div></div>";

// Navigation spinner: shows immediately when switching tabs
echo Html::scriptBlock("
    document.querySelectorAll('[data-creditalert-tab]').forEach(function(link) {
        link.addEventListener('click', function() {
            if (this.classList.contains('active')) return;
            var overlay = document.createElement('div');
            overlay.id = 'creditalert-nav-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;flex-direction:column;';
            overlay.innerHTML = '<div class=\"spinner-border text-primary\" style=\"width:3rem;height:3rem;\" role=\"status\"></div>'
                + '<p class=\"mt-3 text-muted\">" . addslashes(__('Chargement en cours...', 'creditalert')) . "</p>';
            document.body.appendChild(overlay);
        });
    });
");

// Cartes de filtre : bandeau d'en-tête (icône, titre, recherches sauvegardées de GLPI)
echo "<style>
.creditalert-filter-header { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
    background-color: rgba(var(--tblr-azure-rgb, 66, 153, 225), .10); }
.creditalert-filter-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
    width: 2.25rem; height: 2.25rem; border-radius: .5rem; font-size: 1.25rem;
    color: rgb(var(--tblr-azure-rgb, 66, 153, 225)); background-color: rgba(var(--tblr-azure-rgb, 66, 153, 225), .16); }
.creditalert-filter-title { margin: 0; font-size: 1.05rem; font-weight: 700; }
.creditalert-filter-card .form-label { font-weight: 600; }
.creditalert-help { cursor: help; }
/* Texte d'invite des listes multiples (« Toutes les entités », « Rechercher un type de crédit... ») en entier */
.creditalert-filter-card .select2-selection--multiple .select2-search--inline,
.creditalert-filter-card .select2-selection--multiple .select2-search__field { min-width: 16em; }
</style>";

// Bulles d'aide (« i », boutons des recherches sauvegardées) des cartes de filtre
$initTooltipsJs = <<<JS
document.querySelectorAll('.creditalert-filter-card [data-bs-toggle="tooltip"]').forEach(function (el) {
    if (window.bootstrap && bootstrap.Tooltip) {
        bootstrap.Tooltip.getOrCreateInstance(el);
    }
});
JS;

PluginCreditalertConfig::ensureViews();

if ($view === 'credits') {
    // Search results (GLPI handles pagination, saved searches and their panel)
    Search::show(PluginCreditalertCreditSummary::class);
} elseif ($view === 'creditalert') {
    /** @var DBmysql $DB */
    global $DB;

    $vcreditsTable  = 'glpi_plugin_creditalert_vcredits';

    $activeEntities = $_SESSION['glpiactiveentities'] ?? [0];
    if (!is_array($activeEntities)) {
        $activeEntities = [(int) $activeEntities];
    } else {
        $activeEntities = array_map('intval', $activeEntities);
    }
    if (empty($activeEntities)) {
        $activeEntities = [0];
    }

    // Filter params from GET
    $caNames = array_values(array_filter(
        array_map(static function ($value) {
            return is_scalar($value) ? (string) $value : '';
        }, (array) ($_GET['ca_names'] ?? [])),
        static function ($value) {
            return $value !== '';
        }
    ));
    $caStatus   = in_array($_GET['ca_status'] ?? 'all', ['all', 'active', 'inactive'], true)
                  ? (string) ($_GET['ca_status'] ?? 'all') : 'all';
    $caSearch   = isset($_GET['ca_search']);
    $caShowOver = !$caSearch || !empty($_GET['ca_show_over']);

    // Unique credit names across all accessible entities (no duplicates)
    $uniqueNames = [];
    foreach ($DB->request([
        'SELECT'  => ['client_label'],
        'FROM'    => $vcreditsTable,
        'WHERE'   => ['entities_id' => $activeEntities],
        'GROUPBY' => ['client_label'],
        'ORDER'   => ['client_label'],
    ]) as $row) {
        $uniqueNames[] = (string) $row['client_label'];
    }
    $namesOptions = empty($uniqueNames) ? [] : array_combine($uniqueNames, $uniqueNames);

    // ── Filter form (saved searches: GLPI's own panel, button and dialog) ────
    echo "<div class='row creditalert-layout'>";
    PluginCreditalertSavedsearch::showPanel(PluginCreditalertCreditItem::class);
    echo "<div class='col'>";

    $caFormAction = $esc($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php');
    echo "<form method='get' action='{$caFormAction}' id='creditalert-alerts-form'>";
    echo Html::hidden('view', ['value' => 'creditalert']);
    echo "<div class='card mb-3 creditalert-filter-card'>";
    echo "<div class='card-header creditalert-filter-header'>";
    echo "<span class='creditalert-filter-icon'><i class='ti ti-alert-octagon'></i></span>";
    echo "<h3 class='creditalert-filter-title'>" . $esc(__('Alertes de crédit — filtrage par nom de crédit', 'creditalert')) . "</h3>";
    echo "<div class='ms-auto d-flex align-items-center gap-1'>";
    echo PluginCreditalertSavedsearch::getButtons(PluginCreditalertCreditItem::class, 'creditalert-alerts-form', 'ca_search');
    echo "</div>";
    echo "</div>";
    echo "<div class='card-body'>";

    echo "<div class='row g-3 align-items-end'>";

    echo "<div class='col-md-7'>";
    echo "<label class='form-label mb-1'>" . $esc(__('Nom du credit', 'creditalert')) . "</label>";
    Dropdown::showFromArray('ca_names', $namesOptions, [
        'multiple' => true,
        'values'   => $caNames,
        'width'    => '100%',
        'disabled' => empty($namesOptions),
    ]);
    echo "</div>";

    echo "<div class='col-md-3'>";
    echo "<label class='form-label mb-1'>" . $esc(__('Afficher', 'creditalert')) . "</label>";
    echo "<select name='ca_status' class='form-select'>";
    foreach ([
        'all'      => __('Tous (actif et inactif)', 'creditalert'),
        'active'   => __('Actif seulement', 'creditalert'),
        'inactive' => __('Inactif seulement', 'creditalert'),
    ] as $val => $label) {
        $sel = $caStatus === $val ? ' selected' : '';
        echo "<option value='" . $esc($val) . "'{$sel}>" . $esc($label) . "</option>";
    }
    echo "</select>";
    echo "</div>";

    echo "<div class='col-md-2'>";
    echo "<label class='form-label mb-1'>" . $esc(__('Depassements', 'creditalert')) . "</label>";
    echo "<div class='form-check form-switch mt-1'>";
    $caOverChecked = $caShowOver ? ' checked' : '';
    echo "<input class='form-check-input' type='checkbox' role='switch' name='ca_show_over' id='ca_show_over_switch' value='1'{$caOverChecked} style='width:3em;height:1.5em;cursor:pointer;'>";
    echo "<label class='form-check-label ms-2' for='ca_show_over_switch'>" . $esc($caShowOver ? __('Oui', 'creditalert') : __('Non', 'creditalert')) . "</label>";
    echo "</div>";
    echo "</div>";

    echo "</div>"; // row

    echo "<div class='d-flex flex-wrap gap-2 mt-3'>";
    echo "<button type='submit' name='ca_search' value='1' class='btn btn-primary'>"
        . "<i class='ti ti-search me-1'></i>" . $esc(__('Afficher les résultats', 'creditalert')) . "</button>";
    echo "</div>";

    echo "</div></div>"; // card-body, card
    Html::closeForm();
    echo Html::scriptBlock($initTooltipsJs);

    // ── Results table ─────────────────────────────────────────────────────────
    if ($caSearch) {
        $caWhere = ['entities_id' => $activeEntities];
        if (!empty($caNames)) {
            $caWhere['client_label'] = $caNames;
        }
        if ($caStatus === 'active') {
            $caWhere['is_active'] = 1;
        } elseif ($caStatus === 'inactive') {
            $caWhere['is_active'] = 0;
        }
        if (!$caShowOver) {
            $caWhere[] = new \Glpi\DBAL\QueryExpression('`quantity_used` <= `quantity_sold`');
        }

        $caRows = [];
        foreach ($DB->request([
            'SELECT' => '*',
            'FROM'   => $vcreditsTable,
            'WHERE'  => $caWhere,
        ]) as $caRow) {
            $caRows[] = $caRow;
        }

        $caNow         = time();
        $caOneMonth    = 30 * 24 * 3600;
        $caThreeMonths = 90 * 24 * 3600;

        // Priority: 1=red, 2=orange, 3=purple, 4=normal, 5=green
        $caPriority = static function (array $row) use ($caNow, $caOneMonth, $caThreeMonths): int {
            $consumed = (float) ($row['quantity_used'] ?? 0);
            $sold     = (float) ($row['quantity_sold'] ?? 0);
            $endDate  = (string) ($row['end_date'] ?? '');
            $endTs    = (!empty($endDate) && strpos($endDate, '0000') === false) ? strtotime($endDate) : false;
            $diff     = $endTs !== false ? $endTs - $caNow : null;

            if ($consumed > $sold) {
                return 3;
            }
            if ($consumed == $sold) {
                return 5;
            }
            // consumed < sold
            if ($diff !== null) {
                if ($diff >= 0 && $diff <= $caOneMonth)    return 1;
                if ($diff >= 0 && $diff <= $caThreeMonths) return 2;
            }
            return 4;
        };

        usort($caRows, static function (array $a, array $b) use ($caPriority): int {
            $pa = $caPriority($a);
            $pb = $caPriority($b);
            if ($pa !== $pb) return $pa - $pb;
            return strcmp((string) ($a['client_label'] ?? ''), (string) ($b['client_label'] ?? ''));
        });

        echo Html::scriptBlock("
if (!document.getElementById('ca-alert-style')) {
    var s = document.createElement('style');
    s.id = 'ca-alert-style';
    s.textContent = '.ca-row-purple{background-color:#cfe2ff!important;}'
        + '.ca-row-purple td{color:#084298!important;}';
    document.head.appendChild(s);
}
");

        $fmtNum = static function (float $n): string {
            return fmod($n, 1.0) == 0.0
                ? number_format($n, 0, ',', ' ')
                : number_format($n, 2, ',', ' ');
        };

        echo "<div class='card'><div class='card-body p-0'><div class='table-responsive'>";
        echo "<table id='ca-results-table' class='table table-hover table-bordered mb-0'>";
        echo "<thead class='table-dark'><tr>";
        echo "<th>" . $esc(__('Entite', 'creditalert')) . "</th>";
        echo "<th>" . $esc(__('Nom du credit', 'creditalert')) . "</th>";
        echo "<th class='text-end'>" . $esc(__('Consomme', 'creditalert')) . "</th>";
        echo "<th class='text-end'>" . $esc(__('Vendu', 'creditalert')) . "</th>";
        echo "<th>" . $esc(__('Date de fin', 'creditalert')) . "</th>";
        echo "<th>" . $esc(__('Statut', 'creditalert')) . "</th>";
        echo "</tr></thead><tbody>";

        if (empty($caRows)) {
            echo "<tr><td colspan='6' class='text-center text-muted py-3'>" . $esc(__('Aucun resultat.', 'creditalert')) . "</td></tr>";
        } else {
            foreach ($caRows as $caRow) {
                $caEntityId   = (int) ($caRow['entities_id'] ?? 0);
                $caEntityName = $decode(Dropdown::getDropdownName('glpi_entities', $caEntityId));
                $caCreditName = (string) ($caRow['client_label'] ?? '');
                $caConsumed   = (float) ($caRow['quantity_used'] ?? 0);
                $caSold       = (float) ($caRow['quantity_sold'] ?? 0);
                $caEndDate    = (string) ($caRow['end_date'] ?? '');
                $caIsActive   = (int) ($caRow['is_active'] ?? 1);

                // Parse end date
                $caEndTs    = null;
                $caDiff     = null;
                $caDateLbl  = '-';
                if (!empty($caEndDate) && strpos($caEndDate, '0000') === false) {
                    $caEndTs = strtotime($caEndDate);
                    if ($caEndTs !== false) {
                        $caDiff    = $caEndTs - $caNow;
                        $caDateLbl = date('d/m/Y', $caEndTs);
                    }
                }

                // Color logic:
                // consumed > sold  → blinking purple (regardless of date)
                // consumed == sold → green (all consumed, contract OK)
                // consumed < sold  → orange (3 months) or red (1 month) based on end date
                //                    orange/red only when consumed != sold
                $caRowAttr   = '';
                $caDateStyle = '';

                if ($caConsumed > $caSold) {
                    $caRowAttr = " class='ca-row-purple'";
                } elseif ($caConsumed == $caSold) {
                    $caRowAttr = " class='table-success'";
                } elseif ($caDiff !== null) {
                    if ($caDiff >= 0 && $caDiff <= $caOneMonth) {
                        $caRowAttr   = " class='table-danger'";
                        $caDateStyle = " style='color:#dc3545;font-weight:bold;'";
                    } elseif ($caDiff >= 0 && $caDiff <= $caThreeMonths) {
                        $caRowAttr   = " class='table-warning'";
                        $caDateStyle = " style='color:#fd7e14;font-weight:bold;'";
                    }
                }

                $caActiveBadge = $caIsActive
                    ? "<span class='badge text-bg-success'>" . $esc(__('Actif', 'creditalert')) . "</span>"
                    : "<span class='badge text-bg-secondary'>" . $esc(__('Inactif', 'creditalert')) . "</span>";

                echo "<tr{$caRowAttr}>";
                echo "<td>" . $esc($caEntityName) . "</td>";
                echo "<td>" . $esc($caCreditName) . "</td>";
                echo "<td class='text-end'>" . $fmtNum($caConsumed) . "</td>";
                echo "<td class='text-end'>" . $fmtNum($caSold) . "</td>";
                echo "<td{$caDateStyle}>" . $esc($caDateLbl) . "</td>";
                echo "<td>{$caActiveBadge}</td>";
                echo "</tr>";
            }
        }

        echo "</tbody></table></div></div></div>";
    }

    echo "</div></div>"; // col, creditalert-layout row
} else {
    /** @var DBmysql $DB */
    global $DB;

    // ── Paramètres du filtre ─────────────────────────────────────────────────
    // Entités facultatives (chacune avec ses sous-entités ; aucune = toutes celles de la session), types de
    // crédit (le nom du crédit contient l'un des mots), dates appliquées aux tickets ou à la période du crédit.
    $entityIds = PluginCreditalertConsumption::normalizeEntityIds($_GET['entities_id'] ?? []);
    $entityScope = PluginCreditalertConsumption::getEntityScope($entityIds);
    $singleEntityId = count($entityIds) === 1 ? $entityIds[0] : 0;
    $types = PluginCreditalertConsumption::parseTypes($_GET['types'] ?? []);
    $dateBegin = $getString('date_begin');
    $dateBegin = PluginCreditalertConsumption::isValidDate($dateBegin) ? $dateBegin : '';
    $dateEnd = $getString('date_end');
    $dateEnd = PluginCreditalertConsumption::isValidDate($dateEnd) ? $dateEnd : '';
    $dateScope = $getString('date_scope') === 'credits' ? 'credits' : 'tickets';
    $filterMode = $getString('filter_mode') === 'tickets' ? 'tickets' : 'credits';
    $selectedCredits = $normalizeIntList($_GET['credits_id'] ?? []);
    $ticketIdsRaw = $getString('ticket_ids');
    $selectedTicketIds = [];
    if ($ticketIdsRaw !== '') {
        $selectedTicketIds = array_values(array_unique(array_filter(
            array_map('intval', preg_split('/[\s,;]+/', $ticketIdsRaw)),
            static function ($v) { return $v > 0; }
        )));
    }
    // « Afficher autres tickets » (tickets sans crédit) : une seule entité, sans type de crédit (un ticket sans
    // crédit n'a pas de type) et dates appliquées aux tickets (il n'a pas non plus de période de crédit).
    $showOtherAllowed = $filterMode === 'credits' && $singleEntityId > 0 && empty($types) && $dateScope === 'tickets';
    $showOther = $showOtherAllowed && !empty($_GET['show_other']);

    // Crédits proposés : ceux des entités choisies, réduits aux types saisis ; un crédit choisi qui n'en fait
    // plus partie (entité ou type retiré) est écarté.
    $creditsOptions = [];
    $creditsStatus = [];
    $creditsEntityLabels = [];
    $creditsBeginLabels = [];
    foreach (PluginCreditalertConsumption::getFilterCredits($entityIds, $types) as $creditId => $credit) {
        $creditsOptions[$creditId] = $credit['label'];
        $creditsStatus[$creditId] = $credit['status'];
        if ($credit['entity_label'] !== '') {
            $creditsEntityLabels[$creditId] = $credit['entity_label'];
        }
        if ($credit['begin_label'] !== '') {
            $creditsBeginLabels[$creditId] = $credit['begin_label'];
        }
    }
    $selectedCredits = array_values(array_filter($selectedCredits, static function ($id) use ($creditsOptions) {
        return isset($creditsOptions[$id]);
    }));

    // Suggestions du champ « Types de crédit » : noms des crédits visibles ; les mots saisis s'y ajoutent
    $typeOptions = [];
    foreach ($types as $type) {
        $typeOptions[$type] = $type;
    }
    foreach (PluginCreditalertConsumption::getCreditNames() as $creditName) {
        if (!isset($typeOptions[$creditName])) {
            $typeOptions[$creditName] = $creditName;
        }
    }

    // ── Fenêtre d'export (formulaire « Exporter les lignes » et « Exporter toutes les pages ») ──────────────
    $dlgRand = mt_rand();
    $dlgSelectHtml = Dropdown::showYesNo('include_private_tasks', 0, -1, [
        'rand'    => $dlgRand,
        'display' => false,
    ]);
    $dlgSelectId = Html::cleanId('dropdown_include_private_tasks' . $dlgRand);
    $dlgFormatHtml = Dropdown::showFromArray('creditalert_export_format', [
        'xlsx' => __('Excel (XLSX)', 'creditalert'),
        'csv'  => __('CSV', 'creditalert'),
    ], [
        'value'   => 'xlsx',
        'rand'    => $dlgRand,
        'display' => false,
    ]);
    $dlgFormatId = Html::cleanId('dropdown_creditalert_export_format' . $dlgRand);
    $dlgTitleJson       = $json(__('Exporter les lignes', 'creditalert'));
    $dlgLabelJson       = $json(__('Inclure les taches privees', 'creditalert'));
    $dlgFormatLabelJson = $json(__('Format d\'export', 'creditalert'));
    $dlgSelectHtmlJson  = $json($dlgSelectHtml);
    $dlgFormatHtmlJson  = $json($dlgFormatHtml);
    $dlgSelectIdJson    = $json($dlgSelectId);
    $dlgFormatIdJson    = $json($dlgFormatId);
    $dlgCancelJson      = $json(__('Annuler', 'creditalert'));
    $dlgExportJson      = $json(__('Exporter', 'creditalert'));
    $js = <<<JS
window.creditalertExportDialog = function (onExport) {
  glpi_html_dialog({
    title: {$dlgTitleJson},
    body: "<div class='d-flex align-items-center justify-content-center gap-2 mb-3'>"
        + "<label class='form-label mb-0' for='" + {$dlgSelectIdJson} + "'>" + {$dlgLabelJson} + "</label>"
        + {$dlgSelectHtmlJson}
        + "</div>"
        + "<div class='d-flex align-items-center justify-content-center gap-2'>"
        + "<label class='form-label mb-0' for='" + {$dlgFormatIdJson} + "'>" + {$dlgFormatLabelJson} + "</label>"
        + {$dlgFormatHtmlJson}
        + "</div>",
    buttons: [
      {
        label: {$dlgCancelJson},
        class: 'btn-secondary'
      },
      {
        label: "<i class='ti ti-download'></i> " + {$dlgExportJson},
        class: 'btn-primary',
        click: function () {
          var sel = document.getElementById({$dlgSelectIdJson});
          var fmt = document.getElementById({$dlgFormatIdJson});
          onExport(!!(sel && sel.value === '1'), (fmt && fmt.value === 'csv') ? 'csv' : 'xlsx');
        }
      }
    ]
  });
};
JS;
    echo Html::scriptBlock($js);

    // ── Formulaire de filtre ─────────────────────────────────────────────────
    echo "<div class='row creditalert-layout'>";
    PluginCreditalertSavedsearch::showPanel(PluginCreditalertConsumption::class);
    echo "<div class='col'>";

    $formAction = $esc($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php');
    echo "<form method='get' action='{$formAction}' id='creditalert-consumptions-form' class='mb-3'>";
    echo Html::hidden('view', ['value' => 'consumptions']);
    echo "<div class='card mb-3 creditalert-filter-card'>";
    echo "<div class='card-header creditalert-filter-header'>";
    echo "<span class='creditalert-filter-icon'><i class='ti ti-search'></i></span>";
    echo "<h3 class='creditalert-filter-title'>" . $esc(__('Consommations — filtrage par type de crédit et par client', 'creditalert')) . "</h3>";
    echo "<div class='ms-auto d-flex align-items-center gap-1'>";
    echo PluginCreditalertSavedsearch::getButtons(PluginCreditalertConsumption::class, 'creditalert-consumptions-form', 'search');
    echo "<button type='button' class='btn btn-sm btn-icon btn-ghost-secondary' id='creditalert-filter-toggle'"
        . " data-bs-toggle='collapse' data-bs-target='#creditalert-filter-body' aria-expanded='true'"
        . " aria-controls='creditalert-filter-body' aria-label='" . $esc(__('Afficher ou masquer les filtres', 'creditalert')) . "'>"
        . "<i class='ti ti-chevron-up' id='creditalert-filter-chevron'></i></button>";
    echo "</div>";
    echo "</div>";
    echo "<div class='collapse show' id='creditalert-filter-body'>";
    echo "<div class='card-body'>";

    // Types de crédit
    $typesRand = mt_rand();
    $typesId = Html::cleanId('dropdown_types' . $typesRand);
    echo "<div class='row g-3'>";
    echo "<div class='col-12 col-xxl-8'>";
    echo "<label class='form-label mb-1' for='{$typesId}'>" . $esc(__('Types de crédit', 'creditalert'))
        . $help(__('Le nom du crédit contient l\'un de ces mots (majuscules et accents ignorés). Exemple : PREVENTIVE ou ANTIVIRUS. Tapez un mot puis Entrée, ou choisissez un nom de crédit.', 'creditalert'))
        . "</label>";
    // Liste native de GLPI (boutons « Tout » / « Aucun », recherche de GLPI) ; mise en place différée pour y
    // ajouter la saisie de mots libres (voir le script du formulaire)
    Dropdown::showFromArray('types', $typeOptions, [
        'multiple' => true,
        'values'   => $types,
        'width'    => '100%',
        'rand'     => $typesRand,
        'init'     => false,
    ]);
    echo "</div>";
    echo "</div>";

    // Entité (optionnel) + dates
    $entityRand = mt_rand();
    echo "<div class='row g-3 mt-0'>";
    echo "<div class='col-12 col-lg-6'>";
    echo "<label class='form-label mb-1'>" . $esc(__('Entité (optionnel)', 'creditalert'))
        . $help(__('Chaque entité choisie compte avec ses sous-entités. Vide : toutes les entités visibles dans GLPI avec votre profil.', 'creditalert'))
        . "</label>";
    // Liste native de GLPI à choix multiples (boutons « Tout » / « Aucun ») : entités actives de la session, nom
    // complet ; l'entité racine n'y figure pas (vide = toutes les entités)
    $entityOptions = [];
    $activeEntityIds = $_SESSION['glpiactiveentities'] ?? [];
    $activeEntityIds = array_values(array_filter(array_map('intval', is_array($activeEntityIds) ? $activeEntityIds : [$activeEntityIds])));
    if (!empty($activeEntityIds)) {
        foreach ($DB->request([
            'SELECT' => ['id', 'completename'],
            'FROM'   => Entity::getTable(),
            'WHERE'  => ['id' => $activeEntityIds],
            'ORDER'  => ['completename'],
        ]) as $entityRow) {
            $entityOptions[(int) $entityRow['id']] = $decode($entityRow['completename']);
        }
    }
    Dropdown::showFromArray('entities_id', $entityOptions, [
        'multiple' => true,
        'values'   => $entityIds,
        'width'    => '100%',
        'rand'     => $entityRand,
        'init'     => false,
    ]);
    echo "<div class='alert alert-info d-flex align-items-center gap-2 py-2 px-3 mt-2 mb-0'>";
    echo "<i class='ti ti-info-circle'></i><span>"
        . $esc(__('Laissez « Toutes les entités » pour récupérer les lignes sur l\'ensemble des clients.', 'creditalert'))
        . "</span></div>";
    echo "</div>";

    echo "<div class='col-12 col-lg-6'>";
    echo "<div class='row g-3'>";
    echo "<div class='col-sm-6'>";
    echo "<label class='form-label mb-1'>" . $esc(__('Date de début', 'creditalert')) . "</label>";
    Html::showDateField('date_begin', [
        'value'       => $dateBegin,
        'placeholder' => __('Date de début', 'creditalert'),
        'display'     => true,
    ]);
    echo "</div>";
    echo "<div class='col-sm-6'>";
    echo "<label class='form-label mb-1'>" . $esc(__('Date de fin', 'creditalert')) . "</label>";
    Html::showDateField('date_end', [
        'value'       => $dateEnd,
        'placeholder' => __('Date de fin', 'creditalert'),
        'display'     => true,
    ]);
    echo "</div>";
    echo "</div>";
    // Une seule ligne : « Dates appliquées aux : (•) tickets ⓘ  ( ) crédits ⓘ »
    echo "<div class='d-flex flex-wrap align-items-center gap-3 mt-2'>";
    echo "<span class='fw-semibold'>" . $esc(__('Dates appliquées aux :', 'creditalert')) . "</span>";
    foreach ([
        'tickets' => [__('tickets', 'creditalert'), __('Tickets ouverts ou résolus entre ces dates.', 'creditalert')],
        'credits' => [__('crédits', 'creditalert'), __('Crédits dont la période recoupe ces dates (sans date de début ou de fin : période ouverte de ce côté).', 'creditalert')],
    ] as $scopeValue => [$scopeLabel, $scopeHelp]) {
        $checked = $dateScope === $scopeValue ? ' checked' : '';
        echo "<span class='d-inline-flex align-items-center gap-1 text-nowrap'>";
        echo "<input class='form-check-input m-0' type='radio' name='date_scope' id='creditalert_date_scope_{$scopeValue}'"
            . " value='{$scopeValue}'{$checked}>";
        echo "<label class='mb-0' for='creditalert_date_scope_{$scopeValue}'>" . $esc($scopeLabel) . "</label>"
            . $help($scopeHelp);
        echo "</span>";
    }
    echo "</div>";
    echo "</div>";
    echo "</div>"; // row entité + dates

    // Mode de filtre
    echo "<div class='row g-3 mt-0'>";
    echo "<div class='col-12'>";
    echo "<label class='form-label mb-1'>" . $esc(__('Mode de filtre', 'creditalert')) . "</label>";
    echo "<div class='d-flex gap-3'>";
    foreach (['credits' => __('Par crédits', 'creditalert'), 'tickets' => __('Par tickets', 'creditalert')] as $modeValue => $modeLabel) {
        $checked = $filterMode === $modeValue ? ' checked' : '';
        echo "<div class='form-check mb-0'>";
        echo "<input class='form-check-input' type='radio' name='filter_mode' id='filter_mode_{$modeValue}' value='{$modeValue}'{$checked}>";
        echo "<label class='form-check-label' for='filter_mode_{$modeValue}'>" . $esc($modeLabel) . "</label>";
        echo "</div>";
    }
    echo "</div>";
    echo "</div>";
    echo "</div>";

    // Par crédits : crédits précis des entités choisies, autres tickets
    $creditSelectRand = mt_rand();
    $displayCredits = $filterMode === 'credits' ? '' : 'display:none;';
    echo "<div class='row g-3 mt-0' id='creditalert_filter_credits' style='{$displayCredits}'>";
    echo "<div class='col-12 creditalert-credits-row' id='creditalert_credits_field'" . (empty($entityIds) ? " style='display:none;'" : '') . ">";
    echo "<label class='form-label mb-1'>" . $esc(__('Crédits', 'creditalert'))
        . $help(__('Facultatif quand un type de crédit est saisi : tous les crédits correspondants sont pris.', 'creditalert'))
        . "</label>";
    $activeLabel = $json(__('Actif', 'creditalert'));
    $inactiveLabel = $json(__('Inactif', 'creditalert'));
    $beginLabel = $json(__('Date de debut', 'creditalert'));
    $js = <<<JS
window.creditalertCreditFilterResult = function(item) {
    if (!item.id) {
        return item.text;
    }
    var data = (item.element && item.element.dataset) ? item.element.dataset : {};
    var container = $('<span></span>');
    container.text(item.text);
    var addBadge = function(text, style) {
        var badge = $('<span></span>');
        badge.addClass('badge ms-2');
        if (style) {
            badge.attr('style', style);
        }
        badge.text(text);
        container.append(' ').append(badge);
    };
    if (data.entityLabel) {
        addBadge(data.entityLabel, 'background-color:#ffffff;color:#0d6efd;border:1px solid #0d6efd;');
    }
    if (data.status === 'inactive') {
        addBadge({$inactiveLabel}, 'background-color:#ffffff;color:#6c757d;border:1px solid #6c757d;');
    } else if (data.status === 'active') {
        addBadge({$activeLabel}, 'background-color:#ffffff;color:#1a7f37;border:1px solid #1a7f37;');
    }
    if (data.beginLabel) {
        addBadge({$beginLabel} + ' : ' + data.beginLabel, 'background-color:#ffffff;color:#343a40;border:1px solid #343a40;');
    }
    return container;
};

window.creditalertCreditFilterSelection = function(item) {
    return creditalertCreditFilterResult(item);
};
JS;
    echo Html::scriptBlock($js);
    Dropdown::showFromArray('credits_id', $creditsOptions, [
        'multiple'          => true,
        'values'            => $selectedCredits,
        'width'             => '100%',
        'rand'              => $creditSelectRand,
        'templateResult'    => 'creditalertCreditFilterResult',
        'templateSelection' => 'creditalertCreditFilterSelection',
    ]);
    echo "<small class='text-muted' id='creditalert_credits_empty'" . (!empty($creditsOptions) ? " style='display:none;'" : '') . ">"
        . $esc(__('Aucun crédit pour ces entités et ces types.', 'creditalert')) . "</small>";
    echo "</div>";
    echo "<div class='col-12'>";
    echo "<div class='form-check mb-0'>";
    echo "<input class='form-check-input' type='checkbox' name='show_other' id='creditalert_show_other' value='1'"
        . ($showOther ? ' checked' : '') . ($showOtherAllowed || $filterMode !== 'credits' ? '' : ' disabled') . ">";
    echo "<label class='form-check-label' for='creditalert_show_other'>" . $esc(__('Afficher autres tickets', 'creditalert')) . "</label>";
    echo " <small class='text-muted' id='creditalert_show_other_help'" . ($showOtherAllowed ? " style='display:none;'" : '') . ">"
        . $esc(__('(avec une seule entité, sans type de crédit, dates appliquées aux tickets)', 'creditalert')) . "</small>";
    echo "</div>";
    echo "</div>";
    echo "</div>"; // creditalert_filter_credits

    // Par tickets : liste d'IDs
    $displayTickets = $filterMode === 'tickets' ? '' : 'display:none;';
    echo "<div class='row g-3 mt-0' id='creditalert_filter_tickets' style='{$displayTickets}'>";
    echo "<div class='col-12'>";
    echo "<label class='form-label mb-1'>" . $esc(__('Liste des IDs de tickets (un par ligne)', 'creditalert')) . "</label>";
    echo "<textarea name='ticket_ids' class='form-control' rows='6' placeholder='59250&#10;59268&#10;59267&#10;...'>" . $esc($ticketIdsRaw) . "</textarea>";
    echo "<small class='text-muted'>" . $esc(__('Saisissez les IDs de tickets separes par des retours a la ligne, virgules ou espaces.', 'creditalert')) . "</small>";
    echo "</div>";
    echo "</div>";

    // Boutons
    echo "<div class='d-flex flex-wrap gap-2 mt-4'>";
    echo "<button type='submit' name='search' value='1' class='btn btn-primary'>"
        . "<i class='ti ti-search me-1'></i>" . $esc(__('Afficher les résultats', 'creditalert')) . "</button>";
    echo "<button type='button' class='btn btn-outline-secondary' id='creditalert-export-lines'>"
        . "<i class='ti ti-download me-1'></i>" . $esc(__('Exporter les lignes', 'creditalert')) . "</button>";
    echo "</div>";

    echo "</div></div></div>"; // card-body, collapse, card
    Html::closeForm();

    // Comportement du formulaire : types en mots libres, crédits rechargés sans recharger la page, champs
    // affichés selon le mode, export direct.
    $selectIds = $json([
        'types'   => $typesId,
        'entity'  => Html::cleanId('dropdown_entities_id' . $entityRand),
        'credits' => Html::cleanId('dropdown_credits_id' . $creditSelectRand),
    ]);
    $creditsDataJson = $json([
        'status' => (object) $creditsStatus,
        'entity' => (object) $creditsEntityLabels,
        'begin'  => (object) $creditsBeginLabels,
    ]);
    $ajaxUrlJson = $json($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/ajax/filtercredits.php');
    $textsJson = $json([
        'typesPlaceholder' => __('Rechercher un type de crédit...', 'creditalert'),
        'entityPlaceholder' => __('Toutes les entités', 'creditalert'),
        'contains'         => __('Contient « %s »', 'creditalert'),
    ]);
    // Au chargement complet : après la mise en place des listes de GLPI (entités, crédits), faite elle aussi
    // à ce moment-là et enregistrée avant ce script.
    $js = <<<JS
$(function () {
    var form = document.getElementById('creditalert-consumptions-form');
    if (!form) {
        return;
    }
    var ids = {$selectIds};
    var texts = {$textsJson};
    var ajaxUrl = {$ajaxUrlJson};
    var typesSelect = document.getElementById(ids.types);
    var entitySelect = document.getElementById(ids.entity);
    var creditsSelect = document.getElementById(ids.credits);
    var creditsBlock = document.getElementById('creditalert_filter_credits');
    var creditsField = document.getElementById('creditalert_credits_field');
    var creditsEmpty = document.getElementById('creditalert_credits_empty');
    var ticketsBlock = document.getElementById('creditalert_filter_tickets');
    var showOther = document.getElementById('creditalert_show_other');
    var showOtherHelp = document.getElementById('creditalert_show_other_help');

    // Crédits affichés au chargement : badges entité / actif / date de début
    var initial = {$creditsDataJson};
    if (creditsSelect) {
        Array.prototype.forEach.call(creditsSelect.options, function (opt) {
            if (initial.status[opt.value]) { opt.dataset.status = initial.status[opt.value]; }
            if (initial.entity[opt.value]) { opt.dataset.entityLabel = initial.entity[opt.value]; }
            if (initial.begin[opt.value]) { opt.dataset.beginLabel = initial.begin[opt.value]; }
        });
        if ($(creditsSelect).data('select2')) {
            $(creditsSelect).trigger('change');
        }
    }

    // Types de crédit : la liste native de GLPI (setupAdaptDropdown, mêmes réglages que partout), à laquelle
    // on ajoute seulement les mots libres (« contient ») : select2 ne les accepte qu'à sa création.
    // Entités : liste native de GLPI, avec son texte d'invite
    var entityConfig = window.select2_configs ? window.select2_configs[ids.entity] : null;
    if (entityConfig && typeof window.setupAdaptDropdown === 'function') {
        entityConfig.placeholder = texts.entityPlaceholder;
        window.setupAdaptDropdown(entityConfig);
    }

    var typesConfig = window.select2_configs ? window.select2_configs[ids.types] : null;
    if (typesSelect && typesConfig && typeof window.setupAdaptDropdown === 'function' && $.fn.select2) {
        var originalSelect2 = $.fn.select2;
        var withTags = function (options) {
            if (this.is(typesSelect) && options && typeof options === 'object') {
                var glpiTemplate = options.templateResult;
                options = $.extend({}, options, {
                    tags: true,
                    tokenSeparators: [','],
                    placeholder: texts.typesPlaceholder,
                    createTag: function (params) {
                        var term = $.trim(params.term);
                        return term === '' ? null : {id: term, text: term, newTag: true};
                    },
                    templateResult: function (data) {
                        if (data.newTag) {
                            return $('<span></span>').text(texts.contains.replace('%s', data.text));
                        }
                        return typeof glpiTemplate === 'function' ? glpiTemplate(data) : data.text;
                    }
                });
            }
            return originalSelect2.apply(this, [options].concat(Array.prototype.slice.call(arguments, 1)));
        };
        $.extend(withTags, originalSelect2);
        $.fn.select2 = withTags;
        try {
            window.setupAdaptDropdown(typesConfig);
        } finally {
            $.fn.select2 = originalSelect2;
        }
    } else if (typesConfig && typeof window.setupAdaptDropdown === 'function') {
        window.setupAdaptDropdown(typesConfig);
    }

    var values = function (select) {
        if (!select) {
            return [];
        }
        return ($(select).val() || []).filter(function (value) {
            return value !== '' && value !== '0';
        });
    };
    var checkedValue = function (name) {
        var input = form.querySelector('input[name="' + name + '"]:checked');
        return input ? input.value : '';
    };

    var updateFields = function () {
        var mode = checkedValue('filter_mode') || 'credits';
        var entities = values(entitySelect);
        var types = values(typesSelect);
        creditsBlock.style.display = mode === 'credits' ? '' : 'none';
        ticketsBlock.style.display = mode === 'tickets' ? '' : 'none';
        if (creditsField) {
            creditsField.style.display = entities.length ? '' : 'none';
        }
        var allowed = mode === 'credits' && entities.length === 1 && types.length === 0
            && checkedValue('date_scope') !== 'credits';
        if (showOther) {
            showOther.disabled = !allowed;
            if (!allowed) {
                showOther.checked = false;
            }
        }
        if (showOtherHelp) {
            showOtherHelp.style.display = allowed ? 'none' : '';
        }
    };

    var requestNumber = 0;
    var refreshCredits = function () {
        updateFields();
        if (!creditsSelect) {
            return;
        }
        var entities = values(entitySelect);
        if (!entities.length) {
            return;
        }
        var url = new URL(ajaxUrl, window.location.href);
        entities.forEach(function (id) { url.searchParams.append('entities_id[]', id); });
        values(typesSelect).forEach(function (type) { url.searchParams.append('types[]', type); });
        var current = ++requestNumber;
        fetch(url.toString(), {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (list) {
                if (current !== requestNumber || !Array.isArray(list)) {
                    return;
                }
                var selected = ($(creditsSelect).val() || []).map(String);
                $(creditsSelect).empty();
                list.forEach(function (credit) {
                    var id = String(credit.id);
                    var opt = new Option(credit.text, id, false, selected.indexOf(id) !== -1);
                    opt.dataset.status = credit.status || '';
                    if (credit.entity_label) { opt.dataset.entityLabel = credit.entity_label; }
                    if (credit.begin_label) { opt.dataset.beginLabel = credit.begin_label; }
                    creditsSelect.appendChild(opt);
                });
                $(creditsSelect).trigger('change');
                if (creditsEmpty) {
                    creditsEmpty.style.display = list.length ? 'none' : '';
                }
            })
            .catch(function () {});
    };
    var timer = null;
    var scheduleRefresh = function () {
        clearTimeout(timer);
        timer = setTimeout(refreshCredits, 250);
    };

    if (entitySelect) {
        $(entitySelect).on('change', scheduleRefresh);
    }
    if (typesSelect) {
        $(typesSelect).on('change', scheduleRefresh);
    }
    form.querySelectorAll('input[name="filter_mode"], input[name="date_scope"]').forEach(function (input) {
        input.addEventListener('change', updateFields);
    });
    updateFields();

    // « Exporter les lignes » : même fenêtre que « Exporter toutes les pages », puis recherche + export
    var exportButton = document.getElementById('creditalert-export-lines');
    if (exportButton) {
        exportButton.addEventListener('click', function () {
            window.creditalertExportDialog(function (includePrivate, format) {
                var setHidden = function (name, value) {
                    var input = form.querySelector('input[type="hidden"][name="' + name + '"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        form.appendChild(input);
                    }
                    input.value = value;
                };
                setHidden('search', '1');
                setHidden('creditalert_export_all', '1');
                if (includePrivate) {
                    setHidden('include_private_tasks', '1');
                }
                if (format === 'csv') {
                    setHidden('export_format', 'csv');
                }
                form.submit();
            });
        });
    }
});
JS;
    echo Html::scriptBlock($js);
    echo Html::scriptBlock($initTooltipsJs);

    // Collapse arrow: rotate chevron + remember state across reloads
    $js = <<<JS
(function() {
  var body = document.getElementById('creditalert-filter-body');
  var toggle = document.getElementById('creditalert-filter-toggle');
  var chevron = document.getElementById('creditalert-filter-chevron');
  if (!body || !toggle || !chevron) {
    return;
  }
  var key = 'creditalert_filter_collapsed';
  var setChevron = function(collapsed) {
    chevron.classList.toggle('ti-chevron-up', !collapsed);
    chevron.classList.toggle('ti-chevron-down', collapsed);
    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  };
  try {
    if (localStorage.getItem(key) === '1') {
      body.classList.remove('show');
      setChevron(true);
    }
  } catch (e) {}
  body.addEventListener('shown.bs.collapse', function() {
    setChevron(false);
    try { localStorage.setItem(key, '0'); } catch (e) {}
  });
  body.addEventListener('hidden.bs.collapse', function() {
    setChevron(true);
    try { localStorage.setItem(key, '1'); } catch (e) {}
  });
})();
JS;
    echo Html::scriptBlock($js);

    if (isset($_GET['search'])) {
        $validSearch = true;
        if ($filterMode === 'tickets') {
            if (empty($selectedTicketIds)) {
                $validSearch = false;
                echo "<div class='alert alert-warning'>" . $esc(__('Saisissez au moins un numéro de ticket.', 'creditalert')) . "</div>";
            }
        } elseif (empty($types) && empty($selectedCredits) && !$showOther) {
            $validSearch = false;
            echo "<div class='alert alert-warning'>"
                . $esc(__('Saisissez au moins un type de crédit, ou choisissez une entité et au moins un crédit.', 'creditalert'))
                . "</div>";
        }

        // For ticket filter mode: check entity match and show warnings
        $validTicketIds = [];
        if ($validSearch && $filterMode === 'tickets') {
            $ticketFilterWarnings = [];
            foreach ($selectedTicketIds as $tid) {
                $ticket = new Ticket();
                if (!$ticket->getFromDB($tid)) {
                    $ticketFilterWarnings[] = sprintf(
                        __('Ticket %d exclu de la liste : ticket introuvable.', 'creditalert'),
                        $tid
                    );
                    continue;
                }
                $ticketEntityId = (int) ($ticket->fields['entities_id'] ?? 0);
                $inScope = !empty($entityScope)
                    ? in_array($ticketEntityId, $entityScope, true)
                    : Session::haveAccessToEntity($ticketEntityId);
                if (!$inScope) {
                    $ticketFilterWarnings[] = sprintf(
                        !empty($entityScope)
                            ? __('Ticket %d exclu de la liste : ne correspond pas aux entites choisies.', 'creditalert')
                            : __('Ticket %d exclu de la liste : hors de vos entites.', 'creditalert'),
                        $tid
                    );
                    continue;
                }
                $validTicketIds[] = $tid;
            }
            if (!empty($ticketFilterWarnings)) {
                echo "<div class='alert alert-info' style='margin-bottom:10px;'>";
                echo "<strong>" . $esc(__('Tickets exclus', 'creditalert')) . " :</strong><ul class='mb-0 mt-1'>";
                foreach ($ticketFilterWarnings as $w) {
                    echo "<li>" . $esc($w) . "</li>";
                }
                echo "</ul></div>";
            }
            if (empty($validTicketIds)) {
                echo "<div class='alert alert-warning'>" . $esc(__('Aucun ticket valide ne correspond aux entites choisies.', 'creditalert')) . "</div>";
                $validSearch = false;
            }
        }

        if ($validSearch) {
            $searchParams = Search::manageParams(PluginCreditalertConsumption::class, $_GET, false);
            $stripHiddenCriteria = static function (array $criteria) use (&$stripHiddenCriteria): array {
                $visible = [];
                foreach ($criteria as $criterion) {
                    if (!is_array($criterion)) {
                        continue;
                    }
                    if (!empty($criterion['_hidden'])) {
                        continue;
                    }
                    if (isset($criterion['criteria']) && is_array($criterion['criteria'])) {
                        $criterion['criteria'] = $stripHiddenCriteria($criterion['criteria']);
                    }
                    $visible[] = $criterion;
                }
                return $visible;
            };
            $userCriteria = $_GET['criteria'] ?? [];
            if (!is_array($userCriteria)) {
                $userCriteria = [];
            }
            $criteria = $stripHiddenCriteria($userCriteria);
            if (empty($criteria)) {
                $criteria = \Glpi\Search\Input\QueryBuilder::getDefaultCriteria(PluginCreditalertConsumption::class);
            }

            // Entités choisies (chacune avec ses sous-entités) ; aucune : GLPI limite aux entités de la session
            if (!empty($entityIds)) {
                $entityCriteria = [];
                foreach ($entityIds as $entityId) {
                    $entityCriteria[] = [
                        'link'       => 'OR',
                        'field'      => PluginCreditalertConsumption::OPT_ENTITY,
                        'searchtype' => 'under',
                        'value'      => $entityId,
                        '_hidden'    => true,
                    ];
                }
                $criteria[] = [
                    'link'     => 'AND',
                    'criteria' => $entityCriteria,
                    '_hidden'  => true,
                ];
            }

            if ($filterMode === 'tickets') {
                // Ticket filter mode
                $_SESSION['plugin_creditalert']['tickets_filter'] = $validTicketIds;
                unset($_SESSION['plugin_creditalert']['credits_filter']);
                $criteria[] = [
                    'link'     => 'AND',
                    'criteria' => [
                        [
                            'link'      => 'OR',
                            'field'     => PluginCreditalertConsumption::OPT_TICKET_FILTER,
                            'searchtype'=> 'equals',
                            'value'     => PluginCreditalertConsumption::TICKET_FILTER_SESSION_TOKEN,
                            'virtual'   => true,
                            '_hidden'   => true,
                        ],
                    ],
                    '_hidden'  => true,
                ];
            } else {
                // Credits filter mode
                unset($_SESSION['plugin_creditalert']['tickets_filter']);
                if (!empty($selectedCredits)) {
                    $_SESSION['plugin_creditalert']['credits_filter'] = $selectedCredits;
                } else {
                    unset($_SESSION['plugin_creditalert']['credits_filter']);
                }

                if (!empty($selectedCredits) || $showOther) {
                    $creditCriteria = [];
                    if (!empty($selectedCredits)) {
                        $creditCriteria[] = [
                            'link'      => 'OR',
                            'field'     => PluginCreditalertConsumption::OPT_CREDIT_ID,
                            'searchtype'=> 'equals',
                            'value'     => PluginCreditalertConsumption::CREDIT_FILTER_SESSION_TOKEN,
                            'virtual'   => true,
                            '_hidden'   => true,
                        ];
                    }
                    if ($showOther) {
                        $creditCriteria[] = [
                            'link'      => 'OR',
                            'field'     => PluginCreditalertConsumption::OPT_HAS_CONSUMPTION,
                            'searchtype'=> 'equals',
                            'value'     => 0,
                            'virtual'   => true,
                            '_hidden'   => true,
                        ];
                    }
                    $criteria[] = [
                        'link'     => 'AND',
                        'criteria' => $creditCriteria,
                        '_hidden'  => true,
                    ];
                }
            }

            // Types de crédit : le nom du crédit contient l'un des mots (les deux modes)
            if (!empty($types)) {
                $criteria[] = [
                    'link'       => 'AND',
                    'field'      => PluginCreditalertConsumption::OPT_CREDIT_TYPES,
                    'searchtype' => 'equals',
                    'value'      => implode(PluginCreditalertConsumption::TYPES_SEPARATOR, $types),
                    'virtual'    => true,
                    '_hidden'    => true,
                ];
            }

            if ($dateBegin !== '' || $dateEnd !== '') {
                if ($dateScope === 'credits') {
                    // Crédits dont la période recoupe les dates choisies
                    $criteria[] = [
                        'link'       => 'AND',
                        'field'      => PluginCreditalertConsumption::OPT_CREDIT_PERIOD,
                        'searchtype' => 'equals',
                        'value'      => $dateBegin . '|' . $dateEnd,
                        'virtual'    => true,
                        '_hidden'    => true,
                    ];
                } else {
                    // Tickets ouverts ou résolus entre les dates choisies
                    $dateCriteria = [];
                    $startValue = $dateBegin !== '' ? $dateBegin . ' 00:00:00' : '';
                    $endValue = $dateEnd !== '' ? $dateEnd . ' 23:59:59' : '';

                    if ($startValue !== '' && $endValue !== '') {
                        $openGroup = [
                            'link'     => 'OR',
                            'criteria' => [
                                [
                                    'link'      => 'AND',
                                    'field'     => PluginCreditalertConsumption::OPT_TICKET_DATE,
                                    'searchtype'=> 'morethan',
                                    'value'     => $startValue,
                                    '_hidden'   => true,
                                ],
                                [
                                    'link'      => 'AND',
                                    'field'     => PluginCreditalertConsumption::OPT_TICKET_DATE,
                                    'searchtype'=> 'lessthan',
                                    'value'     => $endValue,
                                    '_hidden'   => true,
                                ],
                            ],
                            '_hidden'  => true,
                        ];
                        $endGroup = [
                            'link'     => 'OR',
                            'criteria' => [
                                [
                                    'link'      => 'AND',
                                    'field'     => PluginCreditalertConsumption::OPT_TICKET_END_DATE,
                                    'searchtype'=> 'morethan',
                                    'value'     => $startValue,
                                    '_hidden'   => true,
                                ],
                                [
                                    'link'      => 'AND',
                                    'field'     => PluginCreditalertConsumption::OPT_TICKET_END_DATE,
                                    'searchtype'=> 'lessthan',
                                    'value'     => $endValue,
                                    '_hidden'   => true,
                                ],
                            ],
                            '_hidden'  => true,
                        ];
                        $dateCriteria[] = $openGroup;
                        $dateCriteria[] = $endGroup;
                    } elseif ($startValue !== '') {
                        $dateCriteria[] = [
                            'link'      => 'OR',
                            'field'     => PluginCreditalertConsumption::OPT_TICKET_DATE,
                            'searchtype'=> 'morethan',
                            'value'     => $startValue,
                            '_hidden'   => true,
                        ];
                        $dateCriteria[] = [
                            'link'      => 'OR',
                            'field'     => PluginCreditalertConsumption::OPT_TICKET_END_DATE,
                            'searchtype'=> 'morethan',
                            'value'     => $startValue,
                            '_hidden'   => true,
                        ];
                    } elseif ($endValue !== '') {
                        $dateCriteria[] = [
                            'link'      => 'OR',
                            'field'     => PluginCreditalertConsumption::OPT_TICKET_DATE,
                            'searchtype'=> 'lessthan',
                            'value'     => $endValue,
                            '_hidden'   => true,
                        ];
                        $dateCriteria[] = [
                            'link'      => 'OR',
                            'field'     => PluginCreditalertConsumption::OPT_TICKET_END_DATE,
                            'searchtype'=> 'lessthan',
                            'value'     => $endValue,
                            '_hidden'   => true,
                        ];
                    }

                    if (!empty($dateCriteria)) {
                        $criteria[] = [
                            'link'     => 'AND',
                            'criteria' => $dateCriteria,
                            '_hidden'  => true,
                        ];
                    }
                }
            }

            $searchParams['criteria'] = array_values($criteria);
            $searchParams['hide_criteria'] = false;
            $_SESSION['glpisearch'][PluginCreditalertConsumption::class]['criteria'] = $searchParams['criteria'];

            // Adresse de la recherche (pagination, tri, retour des actions de masse) : tout le formulaire
            $targetParams = [
                'view'        => 'consumptions',
                'search'      => 1,
                'filter_mode' => $filterMode,
                'date_scope'  => $dateScope,
            ];
            if (!empty($entityIds)) {
                $targetParams['entities_id'] = $entityIds;
            }
            if (!empty($types)) {
                $targetParams['types'] = $types;
            }
            if ($dateBegin !== '') {
                $targetParams['date_begin'] = $dateBegin;
            }
            if ($dateEnd !== '') {
                $targetParams['date_end'] = $dateEnd;
            }
            if ($showOther) {
                $targetParams['show_other'] = 1;
            }
            if ($filterMode === 'tickets') {
                $targetParams['ticket_ids'] = $ticketIdsRaw;
            } elseif (!empty($selectedCredits)) {
                $targetParams['credits_id'] = $selectedCredits;
            }
            $searchParams['target'] = $CFG_GLPI['root_doc']
                . '/plugins/creditalert/front/creditlist.php?' . http_build_query($targetParams);
            $searchParams['addhidden'] = $searchParams['addhidden'] ?? [];
            $addHiddenCriteriaInputs = static function (array $criteria, string $prefix) use (&$addHiddenCriteriaInputs, &$searchParams): void {
                foreach ($criteria as $index => $criterion) {
                    if (!is_array($criterion)) {
                        continue;
                    }
                    $isHidden = !empty($criterion['_hidden']);
                    if ($isHidden) {
                        $searchParams['addhidden'][$prefix . '[' . $index . '][_hidden]'] = 1;
                    }
                    if (isset($criterion['criteria']) && is_array($criterion['criteria'])) {
                        $addHiddenCriteriaInputs($criterion['criteria'], $prefix . '[' . $index . '][criteria]');
                    }
                    if (!$isHidden) {
                        continue;
                    }
                    foreach ($criterion as $key => $value) {
                        if ($key === '_hidden' || $key === 'criteria') {
                            continue;
                        }
                        if (is_array($value)) {
                            continue;
                        }
                        $searchParams['addhidden'][$prefix . '[' . $index . '][' . $key . ']'] = $value;
                    }
                }
            };
            $addHiddenCriteriaInputs($searchParams['criteria'], 'criteria');
            $redirectUrl = $searchParams['target'];

            // « Réaffecter crédit » travaille sur un seul client : proposé avec une seule entité choisie
            $specificActions = [];
            if ($singleEntityId > 0 && Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG)) {
                $specificActions[PluginCreditalertConsumption::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'reassigncredit']
                    = __('Reaffecter credit', 'creditalert');
            }
            if (Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ)) {
                $specificActions[PluginCreditalertConsumption::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'exportcsv']
                    = __('Exporter CSV / XLSX', 'creditalert');
            }

            $searchParams['showmassiveactions'] = !empty($specificActions);
            $searchParams['massiveactionparams'] = [
                'specific_actions' => $specificActions,
                'extraparams'      => [
                    'entities_id' => $singleEntityId,
                    'hidden' => [
                        'entities_id' => $singleEntityId,
                        'show_other'  => $showOther ? 1 : 0,
                        'redirect'    => $redirectUrl,
                    ],
                ],
            ];

            $forcedDisplay = [
                PluginCreditalertConsumption::OPT_TICKET_ID,
                PluginCreditalertConsumption::OPT_TICKET,
                PluginCreditalertConsumption::OPT_CREDIT_LABEL,
                PluginCreditalertConsumption::OPT_ENTITY,
                PluginCreditalertConsumption::OPT_CONSUME_DATE,
                PluginCreditalertConsumption::OPT_CONSUMED,
                PluginCreditalertConsumption::OPT_TICKET_STATUS,
                PluginCreditalertConsumption::OPT_TICKET_DATE,
            ];


            echo "<div class='search_page row'>";
            echo "<div class='col search-container' data-glpi-search-container>";
            Search::showList(PluginCreditalertConsumption::class, $searchParams, $forcedDisplay);
            $hiddenCriteriaInputs = [];
            foreach ($searchParams['addhidden'] as $name => $value) {
                if (strpos($name, 'criteria[') !== 0) {
                    continue;
                }
                $hiddenCriteriaInputs[$name] = (string) $value;
            }
            if (!empty($hiddenCriteriaInputs)) {
                $hiddenJson = $json($hiddenCriteriaInputs);
                $js = <<<JS
(function() {
  var hidden = {$hiddenJson};
  var container = document.querySelector('.search-container[data-glpi-search-container]');
  if (!container) {
    return;
  }
  var applyHidden = function() {
    var forms = container.querySelectorAll('form.search-form-container');
    forms.forEach(function(form) {
      Object.keys(hidden).forEach(function(name) {
        var selector = 'input[type="hidden"][name="' + CSS.escape(name) + '"]';
        if (form.querySelector(selector)) {
          return;
        }
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = hidden[name];
        form.appendChild(input);
      });
    });
  };
  applyHidden();
  if (window.jQuery) {
    $(document).on('search_refresh', 'table.search-results', function() {
      applyHidden();
    });
  }
})();
JS;
                echo Html::scriptBlock($js);
            }
            echo "</div>";
            echo "</div>";
            if ($showOther) {
                PluginCreditalertConsumption::injectOtherTicketRowColors();
            }

            $triggerExport = false;
            if (isset($_GET['creditalert_export_all']) && (int) $_GET['creditalert_export_all'] === 1) {
                $exportParams = $searchParams;
                $exportParams['export_all'] = 1;
                $exportParams['start'] = 0;
                $exportParams['list_limit'] = 0;
                $exportData = Search::getDatas(PluginCreditalertConsumption::class, $exportParams, $forcedDisplay);
                $exportIds = array_keys($exportData['data']['items'] ?? []);
                $exportIds = $normalizeIntList($exportIds);
                if (empty($exportIds)) {
                    echo "<div class='alert alert-warning'>" . $esc(__('Aucun element selectionne.', 'creditalert')) . "</div>";
                } else {
                    $_SESSION['plugin_creditalert']['export_consumptions'] = $exportIds;
                    $_SESSION['plugin_creditalert']['export_include_private'] = !empty($_GET['include_private_tasks']) ? 1 : 0;
                    $_SESSION['plugin_creditalert']['export_format'] = (($_GET['export_format'] ?? '') === 'csv') ? 'csv' : 'xlsx';
                    $triggerExport = true;
                }
            }

            if (isset($_GET['creditalert_export']) && (int) $_GET['creditalert_export'] === 1) {
                $triggerExport = true;
            }

            if ($triggerExport) {
                $exportUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/consumptions.export.php';
                echo "<iframe src='" . $esc($exportUrl) . "' style='display:none' aria-hidden='true'></iframe>";
                $js = <<<JS
(function() {
  try {
    var url = new URL(window.location.href);
    if (url.searchParams.has('creditalert_export')) {
      url.searchParams.delete('creditalert_export');
    }
    if (url.searchParams.has('creditalert_export_all')) {
      url.searchParams.delete('creditalert_export_all');
    }
    if (url.searchParams.has('include_private_tasks')) {
      url.searchParams.delete('include_private_tasks');
    }
    if (url.searchParams.has('export_format')) {
      url.searchParams.delete('export_format');
    }
    window.history.replaceState({}, document.title, url.toString());
  } catch (e) {}
})();
JS;
                echo Html::scriptBlock($js);
            }
        } // end if ($validSearch)
    } // end if (isset search)

    echo "</div></div>"; // col, creditalert-layout row
} // end else (consumptions view)

Html::footer();
