<?php

use Glpi\Application\View\TemplateRenderer;

/**
 * Recherches sauvegardées de GLPI sur les onglets du plugin qui ont leur propre formulaire (« Consommations par
 * client », « Alertes de crédit ») : même bouton, même fenêtre, même panneau que les listes de GLPI.
 *
 * GLPI enregistre l'adresse complète de la page et la rouvre telle quelle (SavedSearch::load() renvoie vers
 * getSearchURL() du type, avec les paramètres enregistrés) : le formulaire du plugin, envoyé en GET, se
 * restaure donc entièrement. Seul le compteur du panneau est coupé pour ces types : GLPI ne compte que ses
 * propres critères, pas les filtres du plugin (types de crédit, entités, période du crédit).
 */
class PluginCreditalertSavedsearch
{
    /** Types dont les recherches enregistrées décrivent un formulaire du plugin. */
    public const FORM_ITEMTYPES = [
        PluginCreditalertConsumption::class,
        PluginCreditalertCreditItem::class,
    ];

    /**
     * Panneau « Recherches sauvegardées » de GLPI pour ce type (masqué tant qu'on ne l'ouvre pas, sauf s'il est
     * épinglé). À placer dans une ligne `search_page row`, à gauche de la colonne du contenu.
     */
    public static function showPanel(string $itemtype): void
    {
        TemplateRenderer::getInstance()->display('layout/parts/saved_searches.html.twig', [
            'itemtype' => $itemtype,
        ]);
    }

    /**
     * Boutons « Recherches sauvegardées » et « Enregistrer la recherche » de GLPI, pour l'en-tête d'un formulaire
     * GET du plugin. La recherche enregistrée est l'adresse du formulaire une fois envoyé ($searchFlag = nom du
     * paramètre qui lance la recherche).
     */
    public static function getButtons(string $itemtype, string $formId, string $searchFlag): string
    {
        $params = [
            'action'   => 'create',
            'itemtype' => $itemtype,
            'type'     => SavedSearch::SEARCH,
        ];
        $loaded = (int) ($_GET['savedsearches_id'] ?? 0);
        if ($loaded > 0) {
            // Recherche ouverte depuis le panneau : GLPI propose de la mettre à jour ou d'en créer une autre
            $params['id'] = $loaded;
        }

        $esc = static function (string $text): string {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        };
        $json = static function ($value): string {
            return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        };

        $html = "<button type='button' class='btn btn-sm btn-icon btn-ghost-secondary show-saved-searches'"
            . " data-itemtype='" . $esc($itemtype) . "' title='" . $esc(__('Show saved searches')) . "'"
            . " data-bs-toggle='tooltip' data-bs-placement='top'><i class='ti ti-bookmarks'></i></button>";
        $html .= "<button type='button' class='btn btn-sm btn-icon btn-ghost-secondary' data-creditalert-save-search='"
            . $esc($formId) . "' title='" . $esc(__('Save current search')) . "' data-bs-toggle='tooltip'"
            . " data-bs-placement='top'><i class='ti ti-bookmark-plus" . ($loaded > 0 ? ' text-yellow' : '') . "'></i></button>";

        $formIdJs = $json($formId);
        $flagJs = $json($searchFlag);
        $paramsJs = $json($params);
        $titleJs = $json(__('Save current search'));
        $closeJs = $json(__('Close'));
        $js = <<<JS
(function () {
    var button = document.querySelector('[data-creditalert-save-search="' + {$formIdJs} + '"]');
    if (!button) {
        return;
    }
    button.addEventListener('click', function () {
        var form = document.getElementById({$formIdJs});
        if (!form) {
            return;
        }
        // Adresse du formulaire une fois envoyé, sans le jeton CSRF ni les champs vides
        var query = new URLSearchParams();
        new FormData(form).forEach(function (value, key) {
            if (typeof value === 'string' && value !== '' && key !== '_glpi_csrf_token' && key !== {$flagJs}) {
                query.append(key, value);
            }
        });
        query.set({$flagJs}, '1');
        var path = new URL(form.getAttribute('action') || window.location.href, window.location.href).pathname;
        var params = Object.assign({}, {$paramsJs}, {url: path + '?' + query.toString()});

        // Même fenêtre que le bouton des listes de GLPI (GenericView), contenu servi par GLPI
        var modal = document.getElementById('creditalert-savedsearch-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'creditalert-savedsearch-modal';
            modal.className = 'modal';
            modal.tabIndex = -1;
            document.body.appendChild(modal);
        }
        modal.innerHTML = '<div class="modal-dialog modal-lg"><div class="modal-content">'
            + '<div class="modal-header"><h5 class="modal-title"></h5>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
            + '<div class="modal-body"></div></div></div>';
        modal.querySelector('.modal-title').textContent = {$titleJs};
        modal.querySelector('.btn-close').setAttribute('aria-label', {$closeJs});
        var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
        $(modal).find('.modal-body').load(CFG_GLPI.root_doc + '/ajax/savedsearch.php', params);
        bsModal.show();
    });
})();
JS;

        return $html . Html::scriptBlock($js);
    }

    /**
     * Recherche par défaut (étoile du panneau) : ouverte à la première visite de l'onglet dans la session,
     * quand on arrive sans filtre. Comme dans les listes de GLPI, revenir sur l'onglet ensuite le montre vide.
     * À appeler avant tout affichage (redirection).
     */
    public static function loadDefault(string $itemtype): void
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $userId = (int) Session::getLoginUserID();
        if ($userId <= 0) {
            return;
        }
        $alreadyLoaded = !empty($_SESSION['plugin_creditalert']['default_loaded'][$itemtype]);
        $_SESSION['plugin_creditalert']['default_loaded'][$itemtype] = true;
        if ($alreadyLoaded) {
            return;
        }
        $default = SavedSearch_User::getDefault($userId, $itemtype);
        $savedSearchId = is_array($default) ? (int) ($default['savedsearches_id'] ?? 0) : 0;
        if ($savedSearchId > 0) {
            Html::redirect($CFG_GLPI['root_doc'] . '/front/savedsearch.php?action=load&id=' . $savedSearchId);
        }
    }

    /**
     * Crochet pre_item_add / pre_item_update de SavedSearch : pas de compteur pour les recherches des
     * formulaires du plugin (GLPI compterait sans les filtres du plugin, donc un nombre faux).
     */
    public static function disableCount(SavedSearch $item): void
    {
        if (!is_array($item->input)) {
            return;
        }
        $itemtype = (string) ($item->input['itemtype'] ?? ($item->fields['itemtype'] ?? ''));
        if (in_array($itemtype, self::FORM_ITEMTYPES, true)) {
            $item->input['do_count'] = SavedSearch::COUNT_NO;
        }
    }

    /**
     * Mise à jour : les favoris maison de l'onglet « Alertes de crédit » deviennent des recherches sauvegardées
     * de GLPI, privées, à leur propriétaire. Relancer la mise à jour ne crée pas de doublon (même nom, même
     * propriétaire). Renvoie false si un favori n'a pas pu être repris : la table est alors gardée.
     */
    public static function migrateFavorites(string $table): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        $ok = true;
        foreach ($DB->request(['FROM' => $table, 'ORDER' => 'id']) as $favorite) {
            try {
                $userId = (int) ($favorite['users_id'] ?? 0);
                $name = trim((string) ($favorite['name'] ?? ''));
                if ($userId <= 0) {
                    continue;
                }
                if ($name === '') {
                    $name = __('Favori', 'creditalert') . ' ' . (int) $favorite['id'];
                }
                $exists = $DB->request([
                    'COUNT' => 'cpt',
                    'FROM'  => 'glpi_savedsearches',
                    'WHERE' => [
                        'itemtype' => PluginCreditalertCreditItem::class,
                        'users_id' => $userId,
                        'name'     => $name,
                    ],
                ])->current();
                if ((int) ($exists['cpt'] ?? 0) > 0) {
                    continue;
                }

                $status = (string) ($favorite['ca_status'] ?? 'all');
                $query = [
                    'view'      => 'creditalert',
                    'ca_search' => 1,
                    'ca_status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
                ];
                if ((int) ($favorite['ca_show_over'] ?? 1) === 1) {
                    $query['ca_show_over'] = 1;
                }
                $names = json_decode((string) ($favorite['ca_names'] ?? ''), true);
                if (is_array($names)) {
                    $names = array_values(array_filter(array_map('strval', $names), static function ($value) {
                        return $value !== '';
                    }));
                    if (!empty($names)) {
                        $query['ca_names'] = $names;
                    }
                }

                $DB->insert('glpi_savedsearches', [
                    'name'         => $name,
                    'type'         => SavedSearch::SEARCH,
                    'itemtype'     => PluginCreditalertCreditItem::class,
                    'users_id'     => $userId,
                    'is_private'   => 1,
                    'entities_id'  => 0,
                    'is_recursive' => 1,
                    'query'        => Toolbox::append_params($query),
                    'do_count'     => SavedSearch::COUNT_NO,
                ]);
            } catch (\Throwable $e) {
                $ok = false;
                trigger_error(
                    sprintf('creditalert : favori %d non repris en recherche sauvegardée (%s)', (int) ($favorite['id'] ?? 0), $e->getMessage()),
                    E_USER_WARNING
                );
            }
        }
        return $ok;
    }
}
