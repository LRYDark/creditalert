<?php

use Glpi\DBAL\QueryExpression;
use Glpi\Toolbox\URL;

class PluginCreditalertConsumption extends CommonDBTM
{
    public static $rightname = PluginCreditalertProfile::RIGHTNAME;
    public $table = 'glpi_plugin_creditalert_vconsumptions';
    public $no_auto_fields = true;
    private const SEARCH_BASE = 3000;
    public const CREDIT_FILTER_SESSION_TOKEN = '__creditalert_credit_filter__';
    public const OPT_CREDIT_ID = self::SEARCH_BASE;
    public const OPT_TICKET = self::SEARCH_BASE + 1;
    public const OPT_CREDIT_LABEL = self::SEARCH_BASE + 2;
    public const OPT_ENTITY = self::SEARCH_BASE + 3;
    public const OPT_CONSUME_DATE = self::SEARCH_BASE + 4;
    public const OPT_CONSUMED = self::SEARCH_BASE + 5;
    public const OPT_TICKET_STATUS = self::SEARCH_BASE + 6;
    public const OPT_TICKET_DATE = self::SEARCH_BASE + 7;
    public const OPT_HAS_CONSUMPTION = self::SEARCH_BASE + 8;
    public const OPT_TICKET_END_DATE = self::SEARCH_BASE + 9;
    public const OPT_TICKET_ID = self::SEARCH_BASE + 10;
    public const OPT_TICKET_FILTER = self::SEARCH_BASE + 11;
    public const TICKET_FILTER_SESSION_TOKEN = '__creditalert_ticket_filter__';

    public static function getTypeName($nb = 0)
    {
        return _n('Consommation de credit', 'Consommations de credit', $nb, 'creditalert');
    }

    public static function getTable($classname = null)
    {
        PluginCreditalertConfig::ensureViews();
        return 'glpi_plugin_creditalert_vconsumptions';
    }

    private static function getSourceTable(): string
    {
        $config = PluginCreditalertConfig::getConfig();
        return $config['consumption_table'] ?? 'glpi_plugin_credit_tickets';
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();
        $itemtype = self::class;
        $table = self::getTable();

        $tab[] = [
            'id'            => self::OPT_CREDIT_ID,
            'table'         => $table,
            'field'         => 'credit_id',
            'name'          => 'credit_id',
            'datatype'      => 'number',
            'massiveaction' => false,
            'nosearch'      => true,
            'nodisplay'     => true,
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'                => self::OPT_TICKET,
            'table'             => $table,
            'field'             => 'ticket_name',
            'name'              => Ticket::getTypeName(1),
            'datatype'          => 'specific',
            'additionalfields'  => ['ticket_id', 'ticket_name'],
            'itemtype'          => $itemtype,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'                => self::OPT_TICKET_ID,
            'table'             => $table,
            'field'             => 'ticket_id',
            'name'              => __('ID du ticket', 'creditalert'),
            'datatype'          => 'specific',
            'additionalfields'  => ['ticket_id'],
            'itemtype'          => $itemtype,
            'nosearch'          => true,
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'       => self::OPT_CREDIT_LABEL,
            'table'    => $table,
            'field'    => 'credit_label',
            'name'     => __('Credit', 'creditalert'),
            'datatype' => 'text',
            'additionalfields' => ['has_consumption'],
            'itemtype' => $itemtype,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => self::OPT_ENTITY,
            'table'         => 'glpi_entities',
            'field'         => 'completename',
            'name'          => Entity::getTypeName(1),
            'datatype'      => 'dropdown',
            'itemlink_type' => Entity::class,
            'itemtype'      => Entity::class,
            'searchtype'    => ['contains', 'notcontains', 'equals', 'notequals'],
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => self::OPT_CONSUME_DATE,
            'table'    => $table,
            'field'    => 'consume_date',
            'name'     => __('Date de consommation', 'creditalert'),
            'datatype' => 'datetime',
            'itemtype' => $itemtype,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => self::OPT_CONSUMED,
            'table'    => $table,
            'field'    => 'consumed',
            'name'     => __('Quantite consommee', 'creditalert'),
            'datatype' => 'number',
            'itemtype' => $itemtype,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => self::OPT_TICKET_STATUS,
            'table'    => $table,
            'field'    => 'ticket_status',
            'name'     => __('Statut', 'creditalert'),
            'datatype' => 'specific',
            'searchtype' => 'equals',
            'itemtype' => $itemtype,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => self::OPT_TICKET_DATE,
            'table'    => $table,
            'field'    => 'ticket_date',
            'name'     => __('Date du ticket', 'creditalert'),
            'datatype' => 'datetime',
            'itemtype' => $itemtype,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'        => self::OPT_HAS_CONSUMPTION,
            'table'     => $table,
            'field'     => 'has_consumption',
            'name'      => 'has_consumption',
            'datatype'  => 'bool',
            'nosearch'  => true,
            'nodisplay' => true,
            'itemtype'  => $itemtype,
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'            => self::OPT_TICKET_FILTER,
            'table'         => $table,
            'field'         => 'ticket_id',
            'name'          => 'ticket_filter',
            'datatype'      => 'number',
            'massiveaction' => false,
            'nosearch'      => true,
            'nodisplay'     => true,
            'itemtype'      => $itemtype,
        ];

        $tab[] = [
            'id'        => self::OPT_TICKET_END_DATE,
            'table'     => $table,
            'field'     => 'ticket_end_date',
            'name'      => __('Date de fin', 'creditalert'),
            'datatype'  => 'datetime',
            'nosearch'  => true,
            'nodisplay' => true,
            'itemtype'  => $itemtype,
            'massiveaction' => false,
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (is_array($values) && !isset($values['name']) && !isset($values['additionalfields'])) {
            $values = current($values);
        }
        switch ($field) {
            case 'ticket_name':
                $ticketId = $values['additionalfields']['ticket_id'] ?? $values['ticket_id'] ?? null;
                $label = $values['name'] ?? $values['ticket_name'] ?? $values ?? '';
                if (!empty($ticketId)) {
                    $url = Ticket::getFormURLWithID((int) $ticketId);
                    return "<a href='{$url}'>" . Html::entities_deep($label) . "</a>";
                }
                return Html::entities_deep($label);
            case 'ticket_id':
                $ticketId = (int) ($values['additionalfields']['ticket_id'] ?? $values['ticket_id'] ?? $values['name'] ?? $values ?? 0);
                if ($ticketId <= 0) {
                    return '';
                }
                $url = Ticket::getFormURLWithID($ticketId);
                return "<a href='{$url}'>" . $ticketId . "</a>";
            case 'ticket_status':
                $status = $values['name'] ?? $values ?? '';
                return Ticket::getStatus((int) $status);
            case 'entities_id':
                return PluginCreditalertConfig::getEntityShortName((int) $values);
            case 'credit_label':
                $label = $values['name'] ?? $values['credit_label'] ?? $values ?? '';
                $additional = $values['additionalfields'] ?? [];
                if ((int) ($additional['has_consumption'] ?? 1) === 0) {
                    $badge = __('Autre ticket', 'creditalert');
                    return "<span class='badge bg-info text-white creditalert-other-ticket'>{$badge}</span>";
                }
                return Html::entities_deep($label);
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'ticket_status':
                $options['value'] = $values[$field] ?? '';
                $choices = Ticket::getAllStatusArray();
                return Dropdown::showFromArray($name, $choices, $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public static function addWhere($link, $nott, $itemtype, $ID, $searchtype, $value)
    {
        $intId = (int) $ID;

        if ($intId === self::OPT_CREDIT_ID && $searchtype === 'equals' && $value === self::CREDIT_FILTER_SESSION_TOKEN) {
            $ids = $_SESSION['plugin_creditalert']['credits_filter'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if (empty($ids)) {
                return '0=1';
            }
            $field = DBmysql::quoteName(self::getTable()) . '.' . DBmysql::quoteName('credit_id');
            $values = array_map(static function ($id) {
                return DBmysql::quoteValue($id);
            }, $ids);
            $operator = $nott ? 'NOT IN' : 'IN';
            return $field . ' ' . $operator . ' (' . implode(', ', $values) . ')';
        }

        if ($intId === self::OPT_TICKET_FILTER && $searchtype === 'equals' && $value === self::TICKET_FILTER_SESSION_TOKEN) {
            $ids = $_SESSION['plugin_creditalert']['tickets_filter'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
            if (empty($ids)) {
                return '0=1';
            }
            $field = DBmysql::quoteName(self::getTable()) . '.' . DBmysql::quoteName('ticket_id');
            $values = array_map(static function ($id) {
                return DBmysql::quoteValue($id);
            }, $ids);
            $operator = $nott ? 'NOT IN' : 'IN';
            return $field . ' ' . $operator . ' (' . implode(', ', $values) . ')';
        }

        return '';
    }

    public static function injectOtherTicketRowColors(): void
    {
        $js = <<<JS
document.querySelectorAll('.creditalert-other-ticket').forEach(function(el) {
    var row = el.closest('tr');
    if (row) {
        row.classList.add('creditalert-row-other');
    }
});
JS;
        echo "<style>
.creditalert-row-other { background-color: #eaf3ff !important; }
</style>";
        echo Html::scriptBlock($js);
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_CONFIG);
    }

    public static function getMassiveActionsForItemtype(
        array &$actions,
        $itemtype,
        $is_deleted = false,
        ?CommonDBTM $checkitem = null
    ) {
        if ($itemtype !== static::class) {
            return;
        }

        $action_prefix = static::class . MassiveAction::CLASS_ACTION_SEPARATOR;
        $actions[$action_prefix . 'reassigncredit'] = __('Reaffecter credit', 'creditalert');
        $actions[$action_prefix . 'exportcsv'] = __('Exporter CSV', 'creditalert');
    }

    public function getSpecificMassiveActions($checkitem = null)
    {
        $actions = [];

        if (Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_CONFIG)) {
            $actions[static::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'reassigncredit']
                = __('Reaffecter credit', 'creditalert');
        }
        if (Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_READ)) {
            $actions[static::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'exportcsv']
                = __('Exporter CSV', 'creditalert');
        }

        return $actions;
    }

    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        if ($ma->getAction() === 'exportcsv') {
            echo "<div class='d-flex align-items-center justify-content-center gap-2 mb-3'>";
            echo "<label class='form-label mb-0'>" . __('Inclure les taches privees', 'creditalert') . "</label>";
            Dropdown::showYesNo('include_private_tasks', 0);
            echo "</div>";
            echo Html::submit(__('Exporter CSV', 'creditalert'), [
                'name'  => 'massiveaction',
                'class' => 'btn btn-primary',
            ]);
            return true;
        }

        if ($ma->getAction() !== 'reassigncredit') {
            return parent::showMassiveActionsSubForm($ma);
        }

        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $input = $ma->getInput();
        $entityId = (int) ($input['entities_id'] ?? ($_POST['entities_id'] ?? ($_GET['entities_id'] ?? 0)));

        // Entity selector
        $entityRand = mt_rand();
        echo "<div class='mb-3'>";
        echo "<label class='form-label mb-1'>" . __('Entite', 'creditalert') . "</label>";
        Dropdown::show('Entity', [
            'name'  => 'reassign_entity_id',
            'value' => $entityId,
            'rand'  => $entityRand,
            'width' => '100%',
        ]);
        echo "</div>";

        // Credit selector
        $credits = self::getCreditsForEntity($entityId);
        echo "<div class='mb-3' id='creditalert_reassign_credit_wrapper'>";
        echo "<label class='form-label mb-1'>" . __('Nouveau credit', 'creditalert') . "</label>";
        $selectId = 'creditalert_new_credit_' . mt_rand();
        $selectIdClean = Html::cleanId($selectId);
        echo "<select name='new_credit_id' id='{$selectIdClean}' class='form-select' style='width: 100%'>";
        echo "<option value=''>" . Dropdown::EMPTY_VALUE . "</option>";
        foreach ($credits as $creditId => $credit) {
            $attr = " data-active='" . ($credit['active'] ? '1' : '0') . "'";
            $attr .= " data-expired='" . ($credit['expired'] ? '1' : '0') . "'";
            if ($credit['begin_year'] !== '') {
                $attr .= " data-begin-year='" . Html::cleanInputText((string) $credit['begin_year']) . "'";
            }
            if ($credit['expire_date'] !== '') {
                $attr .= " data-expire-date='" . Html::cleanInputText((string) $credit['expire_date']) . "'";
            }
            if ($credit['percent'] !== '') {
                $attr .= " data-percent='" . Html::cleanInputText((string) $credit['percent']) . "'";
            }
            if (!empty($credit['entity_label'])) {
                $attr .= " data-entity-label='" . Html::cleanInputText((string) $credit['entity_label']) . "'";
            }
            echo "<option value='" . (int) $creditId . "'{$attr}>";
            echo Html::entities_deep($credit['label']);
            echo "</option>";
        }
        echo "</select>";
        if (empty($credits)) {
            echo "<div class='alert alert-warning mt-2 creditalert-no-credits'>" . __('Aucun credit trouve pour cette entite.', 'creditalert') . "</div>";
        }
        echo "</div>";

        static $scriptLoaded = false;
        if (!$scriptLoaded) {
            $scriptLoaded = true;
            $labelBegin = json_encode(__('Debut', 'creditalert'));
            $labelExpired = json_encode(__('Expire', 'creditalert'));
            $labelActive = json_encode(__('Actif', 'creditalert'));
            $labelInactive = json_encode(__('Inactif', 'creditalert'));
            $labelConsumed = json_encode(__('Consomme', 'creditalert'));
            $js = <<<JS
window.creditalertCreditBadgeResult = function(item) {
    if (!item.id) {
        return item.text;
    }
    var data = item.element && item.element.dataset ? item.element.dataset : {};
    var container = $('<span></span>').css({display:'inline-flex','flex-wrap':'wrap','align-items':'center','gap':'4px'});
    container.append($('<span></span>').text(item.text));
    var addBadge = function(text, cls) {
        var badge = $('<span></span>');
        badge.addClass('badge ' + cls);
        badge.css({color:'#ffffff','font-size':'0.75em','white-space':'nowrap'});
        badge.text(text);
        container.append(badge);
    };
    if (data.entityLabel) {
        addBadge(data.entityLabel, 'bg-info');
    }
    if (data.beginYear) {
        addBadge({$labelBegin} + ' ' + data.beginYear, 'bg-primary');
    }
    if (data.expired === '1') {
        var expireText = {$labelExpired};
        if (data.expireDate) {
            expireText += ' ' + data.expireDate;
        }
        addBadge(expireText, 'bg-danger');
    }
    if (data.active === '1') {
        addBadge({$labelActive}, 'bg-success');
    } else if (data.active === '0') {
        addBadge({$labelInactive}, 'bg-secondary');
    }
    if (data.percent !== undefined && data.percent !== '') {
        addBadge({$labelConsumed} + ' ' + data.percent + '%', 'bg-dark');
    }
    return container;
};

window.creditalertCreditBadgeSelection = function(item) {
    if (!item.id) {
        return item.text;
    }
    var data = item.element && item.element.dataset ? item.element.dataset : {};
    var container = $('<span></span>').css({display:'inline-flex','flex-wrap':'wrap','align-items':'center','gap':'4px','line-height':'1.4'});
    container.append($('<span></span>').text(item.text));
    var addBadge = function(text, cls) {
        var badge = $('<span></span>');
        badge.addClass('badge ' + cls);
        badge.css({color:'#ffffff','font-size':'0.7em','white-space':'nowrap','vertical-align':'middle'});
        badge.text(text);
        container.append(badge);
    };
    if (data.entityLabel) {
        addBadge(data.entityLabel, 'bg-info');
    }
    if (data.beginYear) {
        addBadge({$labelBegin} + ' ' + data.beginYear, 'bg-primary');
    }
    if (data.expired === '1') {
        var expireText = {$labelExpired};
        if (data.expireDate) {
            expireText += ' ' + data.expireDate;
        }
        addBadge(expireText, 'bg-danger');
    }
    if (data.active === '1') {
        addBadge({$labelActive}, 'bg-success');
    } else if (data.active === '0') {
        addBadge({$labelInactive}, 'bg-secondary');
    }
    if (data.percent !== undefined && data.percent !== '') {
        addBadge({$labelConsumed} + ' ' + data.percent + '%', 'bg-dark');
    }
    return container;
};
JS;
            echo Html::scriptBlock($js);
            echo "<style>
#creditalert_reassign_credit_wrapper .select2-container--default .select2-selection--single {
    height: auto !important;
    min-height: 38px;
}
#creditalert_reassign_credit_wrapper .select2-container--default .select2-selection--single .select2-selection__rendered {
    white-space: normal !important;
    line-height: 1.4;
    padding: 4px 28px 4px 8px;
}
</style>";
        }
        echo Html::jsAdaptDropdown($selectIdClean, [
            'width'             => '100%',
            'templateResult'    => 'creditalertCreditBadgeResult',
            'templateSelection' => 'creditalertCreditBadgeSelection',
        ]);

        // JS: reload credits when entity changes
        $ajaxUrl = json_encode($CFG_GLPI['root_doc'] . '/plugins/creditalert/front/ajax/credits.php');
        $emptyLabel = json_encode(Dropdown::EMPTY_VALUE);
        $noCreditLabel = json_encode(__('Aucun credit trouve pour cette entite.', 'creditalert'));
        $entitySelectId = json_encode('dropdown_reassign_entity_id' . $entityRand);
        $creditSelectIdJson = json_encode($selectIdClean);
        $js = <<<JS
(function() {
    var entitySelect = document.getElementById({$entitySelectId});
    if (!entitySelect) return;
    var onChange = function() {
        var entityId = entitySelect.value || '0';
        var creditSelect = document.getElementById({$creditSelectIdJson});
        if (!creditSelect) return;
        // Destroy select2 before modifying
        if (window.jQuery && $(creditSelect).data('select2')) {
            $(creditSelect).select2('destroy');
        }
        creditSelect.innerHTML = '<option value="">' + {$emptyLabel} + '</option>';
        // Remove old no-credits warning
        var wrapper = document.getElementById('creditalert_reassign_credit_wrapper');
        var oldWarn = wrapper ? wrapper.querySelector('.creditalert-no-credits') : null;
        if (oldWarn) oldWarn.remove();

        fetch({$ajaxUrl} + '?entities_id=' + encodeURIComponent(entityId))
            .then(function(r) { return r.json(); })
            .then(function(credits) {
                creditSelect.innerHTML = '<option value="">' + {$emptyLabel} + '</option>';
                credits.forEach(function(c) {
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.label;
                    if (c.active !== undefined) opt.dataset.active = c.active ? '1' : '0';
                    if (c.expired !== undefined) opt.dataset.expired = c.expired ? '1' : '0';
                    if (c.begin_year) opt.dataset.beginYear = c.begin_year;
                    if (c.expire_date) opt.dataset.expireDate = c.expire_date;
                    if (c.percent) opt.dataset.percent = c.percent;
                    if (c.entity_label) opt.dataset.entityLabel = c.entity_label;
                    creditSelect.appendChild(opt);
                });
                if (credits.length === 0 && wrapper) {
                    var warn = document.createElement('div');
                    warn.className = 'alert alert-warning mt-2 creditalert-no-credits';
                    warn.textContent = {$noCreditLabel};
                    wrapper.appendChild(warn);
                }
                // Re-init select2
                if (window.jQuery) {
                    $(creditSelect).select2({
                        width: '100%',
                        templateResult: window.creditalertCreditBadgeResult,
                        templateSelection: window.creditalertCreditBadgeSelection
                    });
                }
            });
    };
    // Listen on both native and select2 change
    entitySelect.addEventListener('change', onChange);
    if (window.jQuery) {
        $(entitySelect).on('change', onChange);
    }
})();
JS;
        echo Html::scriptBlock($js);

        echo Html::submit(__('Appliquer', 'creditalert'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
        return true;
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ) {
        if ($ma->getAction() === 'exportcsv') {
            if (!Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_READ)) {
                $ma->itemDone($item::class, $ids, MassiveAction::ACTION_NORIGHT);
                return;
            }

            $exportIds = array_values(array_filter(array_map('intval', $ids)));
            if (empty($exportIds)) {
                $ma->addMessage(__('Aucun element selectionne.', 'creditalert'));
                $ma->itemDone($item::class, $ids, MassiveAction::ACTION_KO);
                return;
            }

            $input = $ma->getInput();
            $_SESSION['plugin_creditalert']['export_consumptions'] = $exportIds;
            $_SESSION['plugin_creditalert']['export_include_private'] = !empty($input['include_private_tasks']) ? 1 : 0;

            foreach ($exportIds as $id) {
                $ma->itemDone($item::class, $id, MassiveAction::ACTION_OK);
            }

            /** @var array $CFG_GLPI */
            global $CFG_GLPI;
            $redirect = '';
            if (!empty($input['redirect'])) {
                $redirect = URL::sanitizeURL((string) $input['redirect']);
            }
            if (empty($redirect)) {
                $redirect = Html::getBackUrl();
            }
            if (empty($redirect)) {
                $redirect = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/creditlist.php?view=consumptions';
            }
            $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
            $redirect .= $separator . Toolbox::append_params(['creditalert_export' => 1]);
            $ma->setRedirect($redirect);
            return;
        }

        if ($ma->getAction() !== 'reassigncredit') {
            parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
            return;
        }

        if (!Session::haveRight(self::$rightname, PluginCreditalertProfile::RIGHT_CONFIG)) {
            $ma->itemDone($item::class, $ids, MassiveAction::ACTION_NORIGHT);
            return;
        }

        /** @var DBmysql $DB */
        global $DB;

        $config = PluginCreditalertConfig::getConfig();
        $creditTable = $config['credit_table'];
        $fieldEntity = $config['field_entity'];
        $fieldFk = $config['field_fk_usage'];
        $consumptionTable = self::getSourceTable();

        $input = $ma->getInput();
        $newCreditId = (int) ($input['new_credit_id'] ?? 0);
        $entityId = (int) ($input['entities_id'] ?? 0);

        if ($newCreditId <= 0) {
            $ma->itemDone($item::class, $ids, MassiveAction::ACTION_KO);
            $ma->addMessage(__('Veuillez selectionner un credit.', 'creditalert'));
            return;
        }

        $creditWhere = ['id' => $newCreditId];
        if ($entityId > 0) {
            $creditWhere[$fieldEntity] = $entityId;
        }
        $creditExists = $DB->request([
            'COUNT' => 'count',
            'FROM'  => $creditTable,
            'WHERE' => $creditWhere,
        ])->current();

        if ((int) ($creditExists['count'] ?? 0) === 0) {
            $ma->itemDone($item::class, $ids, MassiveAction::ACTION_KO);
            $ma->addMessage(__('Credit cible introuvable.', 'creditalert'));
            return;
        }

        $validIds = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $validIds[] = $id;
            } else {
                $ma->itemDone($item::class, $id, MassiveAction::NO_ACTION);
            }
        }
        if (empty($validIds)) {
            $ma->addMessage(__('Aucune consommation selectionnee.', 'creditalert'));
            return;
        }

        foreach ($validIds as $id) {
            $result = $DB->update(
                $consumptionTable,
                [$fieldFk => $newCreditId],
                ['id' => $id]
            );
            if ($result) {
                $ma->itemDone($item::class, $id, MassiveAction::ACTION_OK);
            } else {
                $ma->itemDone($item::class, $id, MassiveAction::ACTION_KO);
            }
        }
    }

    public static function getCreditsForEntity(int $entityId): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $config = PluginCreditalertConfig::getConfig();
        $creditTable = $config['credit_table'];
        $fieldEntity = $config['field_entity'];
        $fieldClient = $config['field_client'];
        $fieldActive = $config['field_is_active'];
        $fieldEnd = $config['field_end_date'] ?? '';
        $fieldSold = $config['field_sold'] ?? 'quantity';
        $fieldUsed = $config['field_used'] ?? 'consumed';
        $fieldFk = $config['field_fk_usage'] ?? 'plugin_credit_entities_id';
        $consumptionTable = self::getSourceTable();

        $beginField = '';
        foreach (['begin_date', 'date_begin', 'start_date'] as $candidate) {
            if ($DB->fieldExists($creditTable, $candidate)) {
                $beginField = $candidate;
                break;
            }
        }

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

        $where = [];
        if ($entityId > 0) {
            $entityScope = getSonsOf('glpi_entities', $entityId);
            if (!is_array($entityScope)) {
                $entityScope = [$entityId];
            }
            $entityScope = array_values(array_unique(array_filter(array_map('intval', $entityScope))));
            if (empty($entityScope)) {
                $entityScope = [$entityId];
            }
            $where[$fieldEntity] = $entityScope;
        }
        $select = [
            "$creditTable.id AS id",
            "$creditTable.$fieldClient AS name",
            "$creditTable.$fieldSold AS quantity_sold",
            "$creditTable.$fieldEntity AS credit_entity_id",
            new QueryExpression("COALESCE(SUM($consumptionTable.$fieldUsed), 0) AS consumed"),
        ];
        $groupby = [
            "$creditTable.id",
            "$creditTable.$fieldClient",
            "$creditTable.$fieldSold",
        ];
        if (!empty($fieldActive)) {
            $select[] = "$creditTable.$fieldActive AS is_active";
            $groupby[] = "$creditTable.$fieldActive";
        }
        if (!empty($fieldEnd)) {
            $select[] = "$creditTable.$fieldEnd AS end_date";
            $groupby[] = "$creditTable.$fieldEnd";
        }
        if ($beginField !== '') {
            $select[] = "$creditTable.$beginField AS begin_date";
            $groupby[] = "$creditTable.$beginField";
        }

        $credits = [];
        $entityLabels = [];
        foreach ($DB->request([
            'SELECT' => $select,
            'FROM'  => $creditTable,
            'LEFT JOIN' => [
                $consumptionTable => [
                    'ON' => [
                        $consumptionTable => $fieldFk,
                        $creditTable => 'id',
                    ],
                ],
            ],
            'WHERE' => $where,
            'GROUPBY' => $groupby,
            'ORDER' => ["$creditTable.$fieldClient"],
        ]) as $row) {
            $expired = false;
            $expireDate = '';
            if (!empty($fieldEnd) && !empty($row['end_date'])) {
                $expireDate = substr((string) $row['end_date'], 0, 10);
                $ts = strtotime((string) $row['end_date']);
                if ($ts !== false && $ts < time()) {
                    $expired = true;
                }
            }
            $fullEntityLabel = '';
            $entityId = (int) ($row['credit_entity_id'] ?? 0);
            if ($entityId > 0) {
                if (!array_key_exists($entityId, $entityLabels)) {
                    $entityLabels[$entityId] = \Glpi\Toolbox\Sanitizer::decodeHtmlSpecialChars(
                        Dropdown::getDropdownName('glpi_entities', $entityId)
                    );
                }
                $fullEntityLabel = (string) $entityLabels[$entityId];
            }
            $entityLabel = $fullEntityLabel !== ''
                ? $getRelativeEntityLabel($fullEntityLabel, $selectedEntityParts)
                : '';
            $active = true;
            if (!empty($fieldActive)) {
                $active = ((int) ($row['is_active'] ?? 0)) === 1;
            }
            $beginYear = '';
            if ($beginField !== '' && !empty($row['begin_date'])) {
                $ts = strtotime((string) $row['begin_date']);
                if ($ts !== false) {
                    $beginYear = date('Y', $ts);
                }
            }

            $percent = '';
            $sold = (float) ($row['quantity_sold'] ?? 0);
            $consumed = (float) ($row['consumed'] ?? 0);
            if ($sold > 0) {
                $value = ($consumed / $sold) * 100;
                $percent = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
            }
            $credits[(int) $row['id']] = [
                'label'   => $row['name'],
                'entity_label' => $entityLabel,
                'expired' => $expired,
                'expire_date' => $expireDate,
                'active'  => $active,
                'begin_year' => $beginYear,
                'percent' => $percent,
            ];
        }

        return $credits;
    }
}
