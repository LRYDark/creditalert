<?php

class PluginCreditalertPreference extends CommonDBTM
{
    public static $rightname = PluginCreditalertProfile::RIGHTNAME_TRANSFER;
    public $table = 'glpi_plugin_creditalert_preferences';

    private const DEFAULT_ASSIGN = 1;
    private const DEFAULT_REPLACE = 1;

    public static function getTypeName($nb = 0)
    {
        return _n('Transfert de ticket', 'Transferts de ticket', $nb, 'creditalert');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof Preference)) {
            return '';
        }
        if (!Session::haveRight(PluginCreditalertProfile::RIGHTNAME_TRANSFER, PluginCreditalertProfile::RIGHT_TRANSFER)) {
            return '';
        }
        return self::createTabEntry(__('Transfert de ticket', 'creditalert'), 0);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!($item instanceof Preference)) {
            return false;
        }
        if (!Session::haveRight(PluginCreditalertProfile::RIGHTNAME_TRANSFER, PluginCreditalertProfile::RIGHT_TRANSFER)) {
            return false;
        }

        self::showPreferencesForm(Session::getLoginUserID());
        return true;
    }

    public static function getUserPreferences(int $userId): array
    {
        $defaults = [
            'assign_on_transfer'  => self::DEFAULT_ASSIGN,
            'replace_on_transfer' => self::DEFAULT_REPLACE,
        ];
        if ($userId <= 0) {
            return $defaults;
        }

        $pref = new self();
        if ($pref->getFromDBByCrit(['users_id' => $userId])) {
            $defaults['assign_on_transfer'] = (int) ($pref->fields['assign_on_transfer'] ?? self::DEFAULT_ASSIGN);
            $defaults['replace_on_transfer'] = (int) ($pref->fields['replace_on_transfer'] ?? self::DEFAULT_REPLACE);
        }

        return $defaults;
    }

    public static function saveUserPreferences(int $userId, array $input): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $assign = !empty($input['assign_on_transfer']) ? 1 : 0;
        $replace = !empty($input['replace_on_transfer']) ? 1 : 0;

        $pref = new self();
        if ($pref->getFromDBByCrit(['users_id' => $userId])) {
            return (bool) $pref->update([
                'id' => (int) $pref->fields['id'],
                'users_id' => $userId,
                'assign_on_transfer' => $assign,
                'replace_on_transfer' => $replace,
            ]);
        }

        return (bool) $pref->add([
            'users_id' => $userId,
            'assign_on_transfer' => $assign,
            'replace_on_transfer' => $replace,
        ]);
    }

    public static function showPreferencesForm(int $userId): void
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $prefs = self::getUserPreferences($userId);
        $assign = (int) $prefs['assign_on_transfer'] === 1;
        $replace = (int) $prefs['replace_on_transfer'] === 1;

        $formAction = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/preference.form.php';

        echo "<form method='post' action='" . Html::cleanInputText($formAction) . "' class='mb-3'>";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
        echo Html::hidden('users_id', ['value' => $userId]);

        echo "<div class='card'><div class='card-body'>";
        echo "<div class='mb-3'>";
        echo "<div class='form-check'>";
        echo "<input class='form-check-input' type='checkbox' id='creditalert_assign_on_transfer' name='assign_on_transfer' value='1'"
            . ($assign ? ' checked' : '') . ">";
        echo "<label class='form-check-label' for='creditalert_assign_on_transfer'>"
            . __('Associer les utilisateurs transferes dans "Attribue a"', 'creditalert') . "</label>";
        echo "</div>";
        echo "</div>";

        echo "<div class='mb-3'>";
        echo "<div class='form-check'>";
        echo "<input class='form-check-input' type='checkbox' id='creditalert_replace_on_transfer' name='replace_on_transfer' value='1'"
            . ($replace ? ' checked' : '') . ">";
        echo "<label class='form-check-label' for='creditalert_replace_on_transfer'>"
            . __('Remplacer les utilisateurs deja attribues', 'creditalert') . "</label>";
        echo "</div>";
        echo "<div class='form-text'>" . __('Si inactive, les utilisateurs transferes seront ajoutes aux existants.', 'creditalert') . "</div>";
        echo "</div>";

        echo "<div class='text-end'>";
        echo Html::submit(__('Enregistrer', 'creditalert'), ['name' => 'update_prefs', 'class' => 'btn btn-primary']);
        echo "</div>";
        echo "</div></div>";
        echo "</form>";

        $js = <<<JS
(function() {
  var assign = document.getElementById('creditalert_assign_on_transfer');
  var replace = document.getElementById('creditalert_replace_on_transfer');
  if (!assign || !replace) {
    return;
  }
  var toggle = function() {
    replace.disabled = !assign.checked;
  };
  assign.addEventListener('change', toggle);
  toggle();
})();
JS;
        echo Html::scriptBlock($js);
    }
}
