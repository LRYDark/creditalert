<?php

include('../../../inc/includes.php');
/** @var array $CFG_GLPI */
global $CFG_GLPI;

Session::checkRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ);

Html::header(
    PluginCreditalertCreditItem::getTypeName(2),
    $_SERVER['PHP_SELF'],
    'plugins',
    'creditalert',
    'creditlist'
);

// Tabs: consumptions (view) and credits (view)
$view = $_GET['view'] ?? ($_POST['view'] ?? 'consumptions');
$normalizeIntList = static function ($values): array {
    if (!is_array($values)) {
        $values = [$values];
    }
    return array_values(array_filter(array_map('intval', $values)));
};
$expandEntityScope = static function (int $entityId): array {
    static $sonsCache = [];

    if ($entityId <= 0) {
        return [];
    }
    if (!array_key_exists($entityId, $sonsCache)) {
        $sons = getSonsOf('glpi_entities', $entityId);
        if (!is_array($sons)) {
            $sons = [$entityId];
        }
        $sonsCache[$entityId] = array_values(array_unique(array_filter(array_map('intval', $sons))));
        if (empty($sonsCache[$entityId])) {
            $sonsCache[$entityId] = [$entityId];
        }
    }
    return $sonsCache[$entityId];
};

echo "<div class='card mb-3'><div class='card-body'>";
echo "<ul class='nav nav-tabs' id='creditalert-tabs' role='tablist'>";
$tabs = [
    'consumptions' => __('Consommations par client', 'creditalert'),
    'credits'      => __('Synthese des credits', 'creditalert'),
    'creditalert'  => __('Credit Alert', 'creditalert'),
];
foreach ($tabs as $tabKey => $label) {
    $active = $view === $tabKey ? 'active' : '';
    $url = Html::cleanInputText($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php?view=' . $tabKey);
    echo "<li class='nav-item' role='presentation'>";
    echo "<a class='nav-link $active' href='$url' data-creditalert-tab>" . $label . "</a>";
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

PluginCreditalertConfig::ensureViews();

if ($view === 'credits') {
    // Search results (GLPI handles pagination with LIMIT)
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
    $caNames    = array_values(array_filter((array) ($_GET['ca_names'] ?? [])));
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

    // ── Filter form ──────────────────────────────────────────────────────────
    $caFormAction = Html::cleanInputText($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php');
    echo "<div class='card mb-3'><div class='card-body'>";
    echo "<form method='get' action='{$caFormAction}'>";
    echo Html::hidden('view', ['value' => 'creditalert']);

    echo "<div class='row g-3 align-items-end'>";

    echo "<div class='col-md-5'>";
    echo "<label class='form-label mb-1'>" . __('Nom du credit', 'creditalert') . "</label>";
    Dropdown::showFromArray('ca_names', $namesOptions, [
        'multiple' => true,
        'values'   => $caNames,
        'width'    => '100%',
        'disabled' => empty($namesOptions),
    ]);
    echo "</div>";

    echo "<div class='col-md-2'>";
    echo "<label class='form-label mb-1'>" . __('Afficher', 'creditalert') . "</label>";
    echo "<select name='ca_status' class='form-select'>";
    foreach ([
        'all'      => __('Tous (actif et inactif)', 'creditalert'),
        'active'   => __('Actif seulement', 'creditalert'),
        'inactive' => __('Inactif seulement', 'creditalert'),
    ] as $val => $label) {
        $sel = $caStatus === $val ? ' selected' : '';
        echo "<option value='" . htmlspecialchars($val) . "'{$sel}>" . htmlspecialchars($label) . "</option>";
    }
    echo "</select>";
    echo "</div>";

    echo "<div class='col-md-2'>";
    echo "<label class='form-label mb-1'>" . __('Depassements', 'creditalert') . "</label>";
    echo "<div class='form-check form-switch mt-1'>";
    $caOverChecked = $caShowOver ? ' checked' : '';
    echo "<input class='form-check-input' type='checkbox' role='switch' name='ca_show_over' id='ca_show_over_switch' value='1'{$caOverChecked} style='width:3em;height:1.5em;cursor:pointer;'>";
    echo "<label class='form-check-label ms-2' for='ca_show_over_switch'>" . ($caShowOver ? __('Oui', 'creditalert') : __('Non', 'creditalert')) . "</label>";
    echo "</div>";
    echo "</div>";

    echo "<div class='col-md-3'>";
    echo "<label class='form-label mb-1'>" . __('Sauvegarder en favori', 'creditalert') . "</label>";
    echo "<div class='input-group'>";
    echo "<input type='text' id='ca_fav_name_input' class='form-control' placeholder='" . htmlspecialchars(__('Nom du favori...', 'creditalert')) . "'>";
    echo "<button type='button' class='btn btn-outline-warning' onclick='creditalertSaveFav()'>";
    echo "<i class='ti ti-star'></i> " . __('Sauvegarder', 'creditalert');
    echo "</button>";
    echo "</div>";
    echo "</div>";

    echo "</div>"; // row

    echo "<div class='row g-3 mt-1 align-items-center'>";
    echo "<div class='col-auto'><small class='text-muted fw-semibold'>" . __('Favoris :', 'creditalert') . "</small></div>";
    echo "<div class='col' id='ca-fav-list'></div>";
    echo "</div>";

    echo "<div class='mt-3 text-end'>";
    echo Html::submit(__('Rechercher', 'creditalert'), ['name' => 'ca_search', 'class' => 'btn btn-primary']);
    echo "</div>";

    Html::closeForm();
    echo "</div></div>";

    // ── Favorites JS (DB-backed via AJAX) ────────────────────────────────────
    $caJsBaseUrl   = json_encode($caFormAction, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $caJsNames     = json_encode(array_values($caNames), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $caJsStatus    = json_encode($caStatus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $caJsShowOver  = json_encode($caShowOver ? '1' : '0', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $caJsNoName    = json_encode(__('Veuillez saisir un nom pour ce favori.', 'creditalert'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $caJsNone      = addslashes(__('Aucun favori sauvegarde', 'creditalert'));
    $caJsAjaxUrl   = json_encode(
        Html::cleanInputText($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/ajax/favorites.php'),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );
    $caJsCsrfToken = json_encode(Session::getNewCSRFToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    echo Html::scriptBlock("
(function() {
    var ajaxUrl  = {$caJsAjaxUrl};
    var baseUrl  = {$caJsBaseUrl};
    var csrfToken = {$caJsCsrfToken};
    var favCache = null;

    function escH(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;');
    }

    function renderFavs(favs) {
        favCache = favs;
        var el = document.getElementById('ca-fav-list');
        if (!el) return;
        if (!favs.length) {
            el.innerHTML = '<span class=\"text-muted small\">{$caJsNone}</span>';
            return;
        }
        el.innerHTML = '';
        favs.forEach(function(fav) {
            var span = document.createElement('span');
            span.className = 'badge bg-warning text-dark me-1 d-inline-flex align-items-center gap-1';
            span.innerHTML = '<span style=\"cursor:pointer\" onclick=\"creditalertLoadFav(' + fav.id + ')\" title=\"Charger ce filtre\">' + escH(fav.name) + '</span>'
                + ' <span style=\"cursor:pointer;font-size:1.1em;line-height:1\" onclick=\"creditalertDelFav(' + fav.id + ')\" title=\"Supprimer\">&times;</span>';
            el.appendChild(span);
        });
    }

    var ajaxHeaders = {
        'X-Requested-With': 'XMLHttpRequest',
        'X-Glpi-Csrf-Token': csrfToken
    };

    function ajaxGet(url) {
        return fetch(url, { credentials: 'same-origin', headers: ajaxHeaders });
    }

    function ajaxPost(url, data) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: ajaxHeaders, body: data });
    }

    function loadFavs() {
        ajaxGet(ajaxUrl + '?action=list')
            .then(function(r) { return r.json(); })
            .then(function(data) { renderFavs(Array.isArray(data) ? data : []); })
            .catch(function() { renderFavs([]); });
    }

    window.creditalertSaveFav = function() {
        var inp  = document.getElementById('ca_fav_name_input');
        var name = inp ? inp.value.trim() : '';
        if (!name) { alert({$caJsNoName}); return; }
        var names    = {$caJsNames};
        var status   = {$caJsStatus};
        var showOver = {$caJsShowOver};
        if (window.jQuery) {
            var sel = jQuery('select[name=\"ca_names[]\"]');
            if (sel.length) { names = sel.val() || []; }
        }
        var switchEl = document.getElementById('ca_show_over_switch');
        if (switchEl) { showOver = switchEl.checked ? '1' : '0'; }
        var body = new FormData();
        body.append('action', 'save');
        body.append('name', name);
        body.append('ca_status', status);
        body.append('ca_show_over', showOver);
        names.forEach(function(n) { body.append('ca_names[]', n); });
        ajaxPost(ajaxUrl, body)
            .then(function(r) { return r.json(); })
            .then(function() { loadFavs(); })
            .catch(function() {});
        if (inp) inp.value = '';
    };

    window.creditalertLoadFav = function(id) {
        if (!favCache) return;
        var fav = favCache.find(function(f) { return f.id === id; });
        if (!fav) return;
        var url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('view', 'creditalert');
        url.searchParams.set('ca_status', fav.ca_status || 'all');
        url.searchParams.set('ca_search', '1');
        if (fav.ca_show_over !== undefined) {
            if (fav.ca_show_over === '1' || fav.ca_show_over === 1) {
                url.searchParams.set('ca_show_over', '1');
            } else {
                url.searchParams.delete('ca_show_over');
            }
        }
        url.searchParams.delete('ca_names[]');
        if (fav.ca_names && fav.ca_names.length) {
            fav.ca_names.forEach(function(n) { url.searchParams.append('ca_names[]', n); });
        }
        window.location.href = url.toString();
    };

    window.creditalertDelFav = function(id) {
        var body = new FormData();
        body.append('action', 'delete');
        body.append('id', id);
        ajaxPost(ajaxUrl, body)
            .then(function() { loadFavs(); })
            .catch(function() {});
    };

    loadFavs();
})();
");

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
        echo "<th>" . __('Entite', 'creditalert') . "</th>";
        echo "<th>" . __('Nom du credit', 'creditalert') . "</th>";
        echo "<th class='text-end'>" . __('Consomme', 'creditalert') . "</th>";
        echo "<th class='text-end'>" . __('Vendu', 'creditalert') . "</th>";
        echo "<th>" . __('Date de fin', 'creditalert') . "</th>";
        echo "<th>" . __('Statut', 'creditalert') . "</th>";
        echo "</tr></thead><tbody>";

        if (empty($caRows)) {
            echo "<tr><td colspan='6' class='text-center text-muted py-3'>" . __('Aucun resultat.', 'creditalert') . "</td></tr>";
        } else {
            foreach ($caRows as $caRow) {
                $caEntityId   = (int) ($caRow['entities_id'] ?? 0);
                $caEntityName = \Glpi\Toolbox\Sanitizer::decodeHtmlSpecialChars(
                    Dropdown::getDropdownName('glpi_entities', $caEntityId)
                );
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
                    ? "<span class='badge text-bg-success'>" . __('Actif', 'creditalert') . "</span>"
                    : "<span class='badge text-bg-secondary'>" . __('Inactif', 'creditalert') . "</span>";

                echo "<tr{$caRowAttr}>";
                echo "<td>" . Html::entities_deep($caEntityName) . "</td>";
                echo "<td>" . Html::entities_deep($caCreditName) . "</td>";
                echo "<td class='text-end'>" . $fmtNum($caConsumed) . "</td>";
                echo "<td class='text-end'>" . $fmtNum($caSold) . "</td>";
                echo "<td{$caDateStyle}>" . htmlspecialchars($caDateLbl) . "</td>";
                echo "<td>{$caActiveBadge}</td>";
                echo "</tr>";
            }
        }

        echo "</tbody></table></div></div></div>";
    }
} else {
    $config = PluginCreditalertConfig::getConfig();
    /** @var DBmysql $DB */
    global $DB;

    $entityId = (int) ($_GET['entities_id'] ?? 0);
    $dateBegin = $_GET['date_begin'] ?? '';
    $dateEnd = $_GET['date_end'] ?? '';
    $showOther = !empty($_GET['show_other']);
    $filterMode = $_GET['filter_mode'] ?? 'credits';
    if (!in_array($filterMode, ['credits', 'tickets'], true)) {
        $filterMode = 'credits';
    }
    $selectedCredits = $_GET['credits_id'] ?? [];
    $selectedCredits = $normalizeIntList($selectedCredits);
    $ticketIdsRaw = $_GET['ticket_ids'] ?? '';
    $selectedTicketIds = [];
    if (is_string($ticketIdsRaw) && $ticketIdsRaw !== '') {
        $selectedTicketIds = array_values(array_unique(array_filter(
            array_map('intval', preg_split('/[\s,;]+/', $ticketIdsRaw)),
            static function ($v) { return $v > 0; }
        )));
    }

    $entityScope = [];
    if ($entityId > 0) {
        $entityScope = $expandEntityScope($entityId);
    }

    $creditTable = $config['credit_table'];
    $fieldEntity = $config['field_entity'];
    $fieldClient = $config['field_client'];
    $fieldActive = $config['field_is_active'];
    $creditListTable = $creditTable;
    $creditLabelField = $fieldClient;
    $creditEntityField = $fieldEntity;
    $selectedEntityLabel = '';
    $selectedEntityParts = [];
    if ($entityId > 0) {
        $selectedEntityLabel = \Glpi\Toolbox\Sanitizer::decodeHtmlSpecialChars(
            Dropdown::getDropdownName('glpi_entities', $entityId)
        );
        $selectedEntityParts = array_values(array_filter(
            array_map('trim', explode('>', $selectedEntityLabel)),
            static function ($part) {
                return $part !== '';
            }
        ));
    }
    $getRelativeEntityLabel = static function (string $fullLabel, array $selectedParts): string {
        $parts = array_values(array_filter(
            array_map('trim', explode('>', $fullLabel)),
            static function ($part) {
                return $part !== '';
            }
        ));
        if (empty($parts)) {
            return $fullLabel;
        }
        if (!empty($selectedParts) && count($parts) >= count($selectedParts)) {
            $match = true;
            foreach ($selectedParts as $index => $part) {
                if (!isset($parts[$index]) || $parts[$index] !== $part) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                if (count($parts) === count($selectedParts)) {
                    return (string) end($parts);
                }
                $relative = array_slice($parts, count($selectedParts) - 1);
                return implode(' > ', $relative);
            }
        }
        return (string) end($parts);
    };
    $creditsOptions = [];
    $creditsStatus = [];
    $creditsEntityDepth = [];
    $creditsEntityLabels = [];
    $creditsBeginLabels = [];
    $entityLabels = [];
    if ($entityId > 0) {
        $beginField = '';
        foreach (['begin_date', 'date_begin', 'start_date'] as $candidate) {
            if ($DB->fieldExists($creditListTable, $candidate)) {
                $beginField = $candidate;
                break;
            }
        }
        $creditEntityScope = $entityScope ?: [$entityId];
        $query = [
            'SELECT' => [
                "$creditListTable.id AS id",
                "$creditListTable.$creditLabelField AS name",
                "$creditListTable.$creditEntityField AS credit_entity_id",
            ],
            'FROM'   => $creditListTable,
            'WHERE'  => [
                "$creditListTable.$creditEntityField" => $creditEntityScope,
            ],
            'ORDER'  => [
                "$creditListTable.$creditLabelField",
            ],
        ];
        if (!empty($fieldActive)) {
            $query['SELECT'][] = "$creditListTable.$fieldActive AS is_active";
        }
        if ($beginField !== '') {
            $query['SELECT'][] = "$creditListTable.$beginField AS begin_date";
        }
        foreach ($DB->request($query) as $row) {
            $creditId = (int) ($row['id'] ?? 0);
            if ($creditId <= 0) {
                continue;
            }
            $creditName = (string) ($row['name'] ?? '');
            $creditEntityId = (int) ($row['credit_entity_id'] ?? 0);
            $fullEntityLabel = '';
            if ($creditEntityId > 0) {
                if (!array_key_exists($creditEntityId, $entityLabels)) {
                    $entityLabels[$creditEntityId] = \Glpi\Toolbox\Sanitizer::decodeHtmlSpecialChars(
                        Dropdown::getDropdownName('glpi_entities', $creditEntityId)
                    );
                }
                $fullEntityLabel = (string) $entityLabels[$creditEntityId];
            }
            $entityLabel = $fullEntityLabel !== ''
                ? $getRelativeEntityLabel($fullEntityLabel, $selectedEntityParts)
                : '';
            $optionLabel = trim($creditName);
            if ($optionLabel === '') {
                $optionLabel = $entityLabel;
            }
            $depth = $fullEntityLabel === '' ? PHP_INT_MAX : substr_count($fullEntityLabel, '>');
            if (!isset($creditsOptions[$creditId]) || $depth < ($creditsEntityDepth[$creditId] ?? PHP_INT_MAX)) {
                $creditsOptions[$creditId] = $optionLabel;
                $creditsEntityDepth[$creditId] = $depth;
                $creditsEntityLabels[$creditId] = $entityLabel;
            }
            if (!empty($fieldActive)) {
                if (!isset($creditsStatus[$creditId])) {
                    $creditsStatus[$creditId] = ((int) ($row['is_active'] ?? 0)) === 1 ? 'active' : 'inactive';
                }
            } else {
                if (!isset($creditsStatus[$creditId])) {
                    $creditsStatus[$creditId] = 'active';
                }
            }
            if ($beginField !== '' && !isset($creditsBeginLabels[$creditId]) && !empty($row['begin_date'])) {
                $ts = strtotime((string) $row['begin_date']);
                if ($ts !== false) {
                    $creditsBeginLabels[$creditId] = date('m/Y', $ts);
                }
            }
        }
    }

    echo "<form method='get' action='" . $CFG_GLPI['root_doc'] . "/plugins/creditalert/front/creditlist.php' class='mb-3'>";
    echo Html::hidden('view', ['value' => 'consumptions']);
    echo "<div class='card mb-3'><div class='card-body'>";
    echo "<div class='row g-3 align-items-end'>";

    echo "<div class='col-md-4'>";
    echo "<label class='form-label mb-1'>" . __('Entite', 'creditalert') . "</label>";
    Dropdown::show('Entity', [
        'name'      => 'entities_id',
        'value'     => $entityId,
        'on_change' => 'this.form.submit();',
    ]);
    echo "</div>";

    echo "<div class='col-md-4'>";
    echo "<label class='form-label mb-1'>" . __('Date de debut', 'creditalert') . "</label>";
    Html::showDateField('date_begin', [
        'value'       => $dateBegin,
        'placeholder' => __('Date de debut', 'creditalert'),
        'display'     => true,
    ]);
    echo "</div>";

    echo "<div class='col-md-4'>";
    echo "<label class='form-label mb-1'>" . __('Date de fin', 'creditalert') . "</label>";
    Html::showDateField('date_end', [
        'value'       => $dateEnd,
        'placeholder' => __('Date de fin', 'creditalert'),
        'display'     => true,
    ]);
    echo "</div>";

    echo "</div>";

    // Filter mode selector
    echo "<div class='row g-3 mt-1'>";
    echo "<div class='col-12'>";
    echo "<label class='form-label mb-1'>" . __('Mode de filtre', 'creditalert') . "</label>";
    echo "<div class='d-flex gap-3'>";
    $checkedCredits = $filterMode === 'credits' ? ' checked' : '';
    $checkedTickets = $filterMode === 'tickets' ? ' checked' : '';
    echo "<div class='form-check'>";
    echo "<input class='form-check-input' type='radio' name='filter_mode' id='filter_mode_credits' value='credits'{$checkedCredits} onchange=\"document.getElementById('creditalert_filter_credits').style.display='';document.getElementById('creditalert_filter_tickets').style.display='none';\">";
    echo "<label class='form-check-label' for='filter_mode_credits'>" . __('Par credits', 'creditalert') . "</label>";
    echo "</div>";
    echo "<div class='form-check'>";
    echo "<input class='form-check-input' type='radio' name='filter_mode' id='filter_mode_tickets' value='tickets'{$checkedTickets} onchange=\"document.getElementById('creditalert_filter_tickets').style.display='';document.getElementById('creditalert_filter_credits').style.display='none';\">";
    echo "<label class='form-check-label' for='filter_mode_tickets'>" . __('Par tickets', 'creditalert') . "</label>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";

    // Credits filter (shown when mode is credits)
    $displayCredits = $filterMode === 'credits' ? '' : 'display:none;';
    echo "<div class='row g-3' id='creditalert_filter_credits' style='{$displayCredits}'>";
    echo "<div class='col-12 creditalert-credits-row'>";
    echo "<label class='form-label mb-1'>" . __('Credits', 'creditalert') . "</label>";
    $creditSelectRand = mt_rand();
    static $creditFilterScriptLoaded = false;
    if (!$creditFilterScriptLoaded) {
        $creditFilterScriptLoaded = true;
        $activeLabel = json_encode(__('Actif', 'creditalert'));
        $inactiveLabel = json_encode(__('Inactif', 'creditalert'));
        $beginLabel = json_encode(__('Date de debut', 'creditalert'));
        $js = <<<JS
window.creditalertCreditFilterResult = function(item) {
    if (!item.id) {
        return item.text;
    }
    var status = '';
    if (item.element && item.element.dataset && item.element.dataset.status) {
        status = item.element.dataset.status;
    }
    var entityLabel = '';
    if (item.element && item.element.dataset && item.element.dataset.entityLabel) {
        entityLabel = item.element.dataset.entityLabel;
    }
    var beginValue = '';
    if (item.element && item.element.dataset && item.element.dataset.beginLabel) {
        beginValue = item.element.dataset.beginLabel;
    }
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
    if (entityLabel) {
        addBadge(entityLabel, 'background-color:#ffffff;color:#0d6efd;border:1px solid #0d6efd;');
    }
    if (status === 'inactive') {
        addBadge({$inactiveLabel}, 'background-color:#ffffff;color:#6c757d;border:1px solid #6c757d;');
    } else if (status === 'active') {
        addBadge({$activeLabel}, 'background-color:#ffffff;color:#1a7f37;border:1px solid #1a7f37;');
    }
    if (beginValue) {
        addBadge({$beginLabel} + ' : ' + beginValue, 'background-color:#ffffff;color:#343a40;border:1px solid #343a40;');
    }
    return container;
};

window.creditalertCreditFilterSelection = function(item) {
    return creditalertCreditFilterResult(item);
};
JS;
        echo Html::scriptBlock($js);
    }
    Dropdown::showFromArray('credits_id', $creditsOptions, [
        'multiple' => true,
        'values'   => $selectedCredits,
        'disabled' => empty($creditsOptions),
        'width'    => '100%',
        'rand'     => $creditSelectRand,
        'templateResult' => 'creditalertCreditFilterResult',
        'templateSelection' => 'creditalertCreditFilterSelection',
    ]);
    if (!empty($creditsStatus) || !empty($creditsEntityLabels) || !empty($creditsBeginLabels)) {
        $selectId = 'dropdown_credits_id' . $creditSelectRand;
        $statusJson = json_encode((object) $creditsStatus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $entityJson = json_encode((object) $creditsEntityLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $beginJson = json_encode((object) $creditsBeginLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $js = <<<JS
(function() {
    var select = document.getElementById('{$selectId}');
    if (!select) {
        return;
    }
    var statuses = {$statusJson};
    var entities = {$entityJson};
    var beginLabels = {$beginJson};
    Object.keys(statuses).forEach(function(id) {
        var opt = select.querySelector('option[value="' + id + '"]');
        if (opt) {
            opt.dataset.status = statuses[id];
        }
    });
    Object.keys(entities).forEach(function(id) {
        var opt = select.querySelector('option[value="' + id + '"]');
        if (opt) {
            opt.dataset.entityLabel = entities[id];
        }
    });
    Object.keys(beginLabels).forEach(function(id) {
        var opt = select.querySelector('option[value="' + id + '"]');
        if (opt) {
            opt.dataset.beginLabel = beginLabels[id];
        }
    });
    if (window.jQuery && $(select).data('select2')) {
        $(select).trigger('change');
    }
})();
JS;
        echo Html::scriptBlock($js);
    }
    echo "</div>";

    echo "</div>"; // close creditalert_filter_credits

    // Tickets filter (shown when mode is tickets)
    $displayTickets = $filterMode === 'tickets' ? '' : 'display:none;';
    $ticketIdsValue = Html::cleanInputText($ticketIdsRaw);
    echo "<div class='row g-3' id='creditalert_filter_tickets' style='{$displayTickets}'>";
    echo "<div class='col-12'>";
    echo "<label class='form-label mb-1'>" . __('Liste des IDs de tickets (un par ligne)', 'creditalert') . "</label>";
    echo "<textarea name='ticket_ids' class='form-control' rows='6' placeholder='59250&#10;59268&#10;59267&#10;...'>{$ticketIdsValue}</textarea>";
    echo "<small class='text-muted'>" . __('Saisissez les IDs de tickets separes par des retours a la ligne, virgules ou espaces.', 'creditalert') . "</small>";
    echo "</div>";
    echo "</div>";

    echo "<div class='row g-3'>";
    echo "<div class='col-md-6'>";
    echo "<div class='form-check mt-2'>";
    echo "<input class='form-check-input' type='checkbox' name='show_other' id='creditalert_show_other' value='1'"
        . ($showOther ? ' checked' : '') . ">";
    echo "<label class='form-check-label' for='creditalert_show_other'>"
        . __('Afficher autres tickets', 'creditalert') . "</label>";
    echo "</div>";
    echo "</div>";

    echo "</div>";
    echo "<div class='mt-3 text-end'>";
    echo Html::submit(__('Rechercher', 'creditalert'), ['name' => 'search', 'class' => 'btn btn-primary']);
    echo "</div>";
    echo "</div></div>";
    Html::closeForm();

    if (isset($_GET['search'])) {
        // Validation depends on filter mode
        $validSearch = false;
        if ($filterMode === 'tickets') {
            $validSearch = $entityId > 0 && !empty($selectedTicketIds);
        } else {
            $validSearch = $entityId > 0 && (!empty($selectedCredits) || $showOther);
        }

        if (!$validSearch) {
            if ($filterMode === 'tickets') {
                echo "<div class='alert alert-warning'>" . __('Veuillez selectionner une entite et saisir au moins un ID de ticket.', 'creditalert') . "</div>";
            } else {
                echo "<div class='alert alert-warning'>" . __('Veuillez selectionner une entite et au moins un credit.', 'creditalert') . "</div>";
            }
        } else {
            // For ticket filter mode: check entity match and show warnings
            $ticketFilterWarnings = [];
            $validTicketIds = [];
            if ($filterMode === 'tickets' && !empty($selectedTicketIds)) {
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
                    if (!in_array($ticketEntityId, $entityScope, true)) {
                        $ticketFilterWarnings[] = sprintf(
                            __('Ticket %d exclu de la liste : ne correspond pas a l\'entite selectionnee.', 'creditalert'),
                            $tid
                        );
                        continue;
                    }
                    $validTicketIds[] = $tid;
                }
                if (!empty($ticketFilterWarnings)) {
                    echo "<div class='alert alert-info' style='margin-bottom:10px;'>";
                    echo "<strong>" . __('Tickets exclus', 'creditalert') . " :</strong><ul class='mb-0 mt-1'>";
                    foreach ($ticketFilterWarnings as $w) {
                        echo "<li>" . Html::entities_deep($w) . "</li>";
                    }
                    echo "</ul></div>";
                }
                if (empty($validTicketIds)) {
                    echo "<div class='alert alert-warning'>" . __('Aucun ticket valide ne correspond a l\'entite selectionnee.', 'creditalert') . "</div>";
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

            $criteria[] = [
                'link'       => 'AND',
                'field'      => PluginCreditalertConsumption::OPT_ENTITY,
                'searchtype' => 'under',
                'value'      => $entityId,
                '_hidden'    => true,
            ];

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

            if ($dateBegin !== '' || $dateEnd !== '') {
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

            $searchParams['criteria'] = array_values($criteria);
            $searchParams['hide_criteria'] = false;
            $_SESSION['glpisearch'][PluginCreditalertConsumption::class]['criteria'] = $searchParams['criteria'];

            $targetParams = [
                'view'        => 'consumptions',
                'search'      => 1,
                'entities_id' => $entityId,
                'date_begin'  => $dateBegin,
                'date_end'    => $dateEnd,
                'show_other'  => $showOther ? 1 : 0,
                'filter_mode' => $filterMode,
            ];
            if ($filterMode === 'tickets') {
                $targetParams['ticket_ids'] = $ticketIdsRaw;
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
            $redirectParams = $targetParams;
            if ($filterMode === 'credits') {
                foreach ($selectedCredits as $cid) {
                    $redirectParams['credits_id'][] = $cid;
                }
            }
            $redirectUrl = $CFG_GLPI['root_doc']
                . '/plugins/creditalert/front/creditlist.php?' . http_build_query($redirectParams);

            $specificActions = [];
            if (Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_CONFIG)) {
                $specificActions[PluginCreditalertConsumption::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'reassigncredit']
                    = __('Reaffecter credit', 'creditalert');
            }
            if (Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_READ)) {
                $specificActions[PluginCreditalertConsumption::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'exportcsv']
                    = __('Exporter CSV', 'creditalert');
            }

            $searchParams['showmassiveactions'] = !empty($specificActions);
            $searchParams['massiveactionparams'] = [
                'specific_actions' => $specificActions,
                'extraparams'      => [
                    'entities_id' => $entityId,
                    'hidden' => [
                        'entities_id' => $entityId,
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

            // Export-all toolbar (real button, not JS-injected)
            $exportAllParams = $_GET;
            unset(
                $exportAllParams['creditalert_export'],
                $exportAllParams['creditalert_export_all'],
                $exportAllParams['include_private_tasks']
            );
            $exportAllParams['creditalert_export_all'] = 1;
            $exportAllUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php';
            if (!empty($exportAllParams)) {
                $exportAllUrl .= '?' . http_build_query($exportAllParams);
            }
            echo "<div class='d-flex justify-content-end align-items-center mb-2'>";
            echo "<button type='button' id='creditalert-export-all-btn' class='btn btn-sm btn-secondary'>";
            echo "<i class='ti ti-download'></i> " . __('Exporter toutes les pages', 'creditalert') . "</button>";
            echo "</div>";

            // Export-all dialog via the native GLPI modal helper
            $dlgRand = mt_rand();
            $dlgSelectHtml = Dropdown::showYesNo('include_private_tasks', 0, -1, [
                'rand'    => $dlgRand,
                'display' => false,
            ]);
            $dlgSelectId        = Html::cleanId('dropdown_include_private_tasks' . $dlgRand);
            $exportAllUrlJson   = json_encode($exportAllUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $dlgTitleJson       = json_encode(__('Exporter toutes les pages', 'creditalert'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $dlgLabelJson       = json_encode(__('Inclure les taches privees', 'creditalert'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $dlgSelectHtmlJson  = json_encode($dlgSelectHtml, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $dlgSelectIdJson    = json_encode($dlgSelectId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $dlgCancelJson      = json_encode(__('Annuler', 'creditalert'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $dlgExportJson      = json_encode(__('Exporter', 'creditalert'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            $js = <<<JS
(function() {
  var btn = document.getElementById('creditalert-export-all-btn');
  if (!btn) {
    return;
  }
  var exportUrl = {$exportAllUrlJson};
  btn.addEventListener('click', function() {
    glpi_html_dialog({
      title: {$dlgTitleJson},
      body: "<div class='d-flex align-items-center justify-content-center gap-2'>"
          + "<label class='form-label mb-0' for='" + {$dlgSelectIdJson} + "'>" + {$dlgLabelJson} + "</label>"
          + {$dlgSelectHtmlJson}
          + "</div>",
      buttons: [
        {
          label: {$dlgCancelJson},
          class: 'btn-secondary'
        },
        {
          label: "<i class='ti ti-download'></i> " + {$dlgExportJson},
          class: 'btn-primary',
          click: function() {
            var sel = document.getElementById({$dlgSelectIdJson});
            var url = new URL(exportUrl, window.location.origin);
            if (sel && sel.value === '1') {
              url.searchParams.set('include_private_tasks', '1');
            }
            window.location.href = url.toString();
          }
        }
      ]
    });
  });
})();
JS;
            echo Html::scriptBlock($js);

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
                $hiddenJson = json_encode($hiddenCriteriaInputs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
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
                    echo "<div class='alert alert-warning'>" . __('Aucun element selectionne.', 'creditalert') . "</div>";
                } else {
                    $_SESSION['plugin_creditalert']['export_consumptions'] = $exportIds;
                    $_SESSION['plugin_creditalert']['export_include_private'] = !empty($_GET['include_private_tasks']) ? 1 : 0;
                    $triggerExport = true;
                }
            }

            if (isset($_GET['creditalert_export']) && (int) $_GET['creditalert_export'] === 1) {
                $triggerExport = true;
            }

            if ($triggerExport) {
                $exportUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/consumptions.export.php';
                echo "<iframe src='" . Html::cleanInputText($exportUrl) . "' style='display:none' aria-hidden='true'></iframe>";
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
    window.history.replaceState({}, document.title, url.toString());
  } catch (e) {}
})();
JS;
                echo Html::scriptBlock($js);
            }
        } // end if ($validSearch)
        } // end else (!$validSearch)
    } // end if (isset search)
} // end else (consumptions view)

Html::footer();
