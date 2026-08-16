<?php

/**
 * Plugin install process.
 *
 * @return bool
 */
function plugin_creditalert_install()
{
    include_once __DIR__ . '/sql/install.php';
    return PluginCreditalertInstall::install();
}

/**
 * Plugin uninstall process.
 *
 * @return bool
 */
function plugin_creditalert_uninstall()
{
    include_once __DIR__ . '/sql/uninstall.php';
    return PluginCreditalertInstall::uninstall();
}
/**
 * Add timeline action to reassign credit from ticket.
 *
 * @param array $params
 * @return void
 */
function plugin_creditalert_timeline_actions(array $params): void
{
    if (empty($params['item']) || !($params['item'] instanceof Ticket)) {
        return;
    }

    $canReassign = Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_REASSIGN);
    if (!$canReassign) {
        return;
    }

    /** @var Ticket $ticket */
    $ticket = $params['item'];
    if ($ticket->isNewItem()) {
        return;
    }

    $ticketId = (int) $ticket->getID();
    if ($ticketId <= 0) {
        return;
    }

    $canedit = false;
    if (Session::haveRight(Entity::$rightname, UPDATE)) {
        $canedit = true;
    } elseif (
        $ticket->canEdit($ticketId)
        && !in_array($ticket->fields['status'], array_merge(Ticket::getSolvedStatusArray(), Ticket::getClosedStatusArray()))
    ) {
        $canedit = true;
    }

    if (!$canedit) {
        return;
    }

    /** @var array $CFG_GLPI */
    global $CFG_GLPI;

    if ($canReassign) {
        $modalId = 'creditalert_reassign_credit_' . $ticketId;
        $url = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/ticket.reassigncredit.php?tickets_id=' . $ticketId;
        $modal = Ajax::createIframeModalWindow($modalId, $url, [
            'width'         => 1500,
            'height'        => 750,
            'dialog_class'  => 'modal-xl',
            'title'         => __('Reaffecter credit', 'creditalert'),
            'reloadonclose' => true,
            'display'       => false,
        ]);

        // Mise en forme de la fenetre : large et centree sur ordinateur, panneau
        // ancre en bas de l'ecran sur telephone (meme comportement que les
        // modals des plugins RP et Gestion). Les largeurs en `vw` etaient
        // auparavant posees en style inline par JS, ce qui rendait la fenetre
        // illisible sur mobile (70% de la largeur d'un telephone).
        $modalCss = <<<'CSS'
        @media (min-width: 769px) {
            .modal.creditalert-modal .modal-dialog {
                width: 70vw;
                min-width: 700px;
                max-width: 1400px;
            }
            .modal.creditalert-modal .modal-body {
                max-height: 82vh;
                overflow-y: auto;
            }
        }

        @media (max-width: 768px) {
            .modal.creditalert-modal .modal-dialog {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                max-width: 100%;
                margin: 0;
                display: flex;
                align-items: flex-end;
                /* le panneau reste a la hauteur de son contenu, quelles que
                   soient les classes Bootstrap ajoutees par le coeur GLPI */
                height: auto !important;
                max-height: 92svh;
            }

            .modal.creditalert-modal .modal-content {
                width: 100%;
                max-height: 92svh;
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                border-bottom: 0;
            }

            .modal.creditalert-modal .modal-body {
                padding: .5rem;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Le panneau glisse depuis le bas */
            .modal.creditalert-modal.fade .modal-dialog {
                transform: translateY(100%);
                transition: transform .25s ease-out;
            }

            .modal.creditalert-modal.show .modal-dialog {
                transform: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .modal.creditalert-modal.fade .modal-dialog { transition: none; }
        }

        /* Barre d'actions du ticket, telephone uniquement.
           `.filter-timeline` (coeur GLPI) accueille les boutons du plugin.
           C'est un element flex retrecissable dont les enfants sont des
           `inline-block` : quand la place manque il se reduit a la largeur
           d'une icone et les boutons s'empilent en colonne, ce qui etire toute
           la barre. On lui rend sa largeur de contenu et on compacte les
           icones ; le bouton principal (« Repondre ») cede la largeur
           necessaire en tronquant son libelle, sans jamais descendre sous la
           taille de son icone. Regles identiques a celles du plugin Transfert
           de ticket : chaque plugin doit fonctionner seul. */
        @media (max-width: 768px) {
            #itil-footer .buttons-bar .timeline-buttons {
                align-items: center;
                min-width: 0;
            }

            #itil-footer .buttons-bar .filter-timeline {
                display: flex;
                flex: 0 0 auto;
                flex-wrap: nowrap;
                align-items: center;
            }

            #itil-footer .buttons-bar .filter-timeline .btn-icon {
                width: 1.9rem;
                min-width: 1.9rem;
                height: 1.9rem;
                padding: 0;
            }

            #itil-footer .buttons-bar .main-actions {
                min-width: 0;
            }

            #itil-footer .buttons-bar .main-actions .btn {
                min-width: 2.75rem;
                overflow: hidden;
            }

            #itil-footer .buttons-bar .main-actions .btn span {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }
        CSS;

        $buttonId = 'creditalert_reassign_btn_' . $ticketId;
        $label = Html::entities_deep(__('Reaffecter credit', 'creditalert'));
        echo "<style id='creditalert_modal_css'>{$modalCss}</style>";
        echo $modal;
        echo "<li class='creditalert-timeline-action'>";
        echo "<span id='{$buttonId}' class='me-1' data-bs-toggle='tooltip' data-bs-placement='top' title='{$label}'>";
        echo "<button type='button' class='btn btn-icon btn-ghost-secondary' data-bs-toggle='modal' data-bs-target='#{$modalId}'>";
        echo "<i class='ti ti-exchange'></i>";
        echo "</button>";
        echo "</span>";
        echo "</li>";
    }

    if ($canReassign) {
        $js = <<<JS
        $(function() {
            // La feuille de style est ecrite dans la liste d'actions de la
            // timeline (seul endroit ou le hook peut produire du HTML) : on la
            // deplace dans le <head> pour ne rien laisser d'etranger dans le
            // DOM du ticket.
            var css = document.getElementById('creditalert_modal_css');
            if (css && css.parentNode !== document.head) {
                document.head.appendChild(css);
            }

            var btn = document.getElementById('{$buttonId}');
            if (!btn) {
                return;
            }
            var target = document.querySelector('.filter-timeline');
            if (!target) {
                return;
            }
            target.insertBefore(btn, target.firstChild);
            var li = btn.closest('li');
            if (li) {
                li.remove();
            }

            var modal = document.getElementById('{$modalId}');
            if (modal) {
                modal.classList.add('creditalert-modal');
            }
        });
    JS;
        echo Html::scriptBlock($js);
    }
}
