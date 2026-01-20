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

function plugin_creditalert_timeline_actions(array $params): void
{
    if (empty($params['item']) || !($params['item'] instanceof Ticket)) {
        return;
    }

    if (!Session::haveRight(PluginCreditalertProfile::$rightname, PluginCreditalertProfile::RIGHT_REASSIGN)) {
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

    $buttonId = 'creditalert_reassign_btn_' . $ticketId;
    $label = Html::entities_deep(__('Reaffecter credit', 'creditalert'));
    echo $modal;
    echo "<li class='creditalert-timeline-action'>";
    echo "<span id='{$buttonId}' class='me-1' data-bs-toggle='tooltip' data-bs-placement='top' title='{$label}'>";
    echo "<button type='button' class='btn btn-icon btn-ghost-secondary' data-bs-toggle='modal' data-bs-target='#{$modalId}'>";
    echo "<i class='ti ti-exchange'></i>";
    echo "</button>";
    echo "</span>";
    echo "</li>";

    $js = <<<JS
        $(function() {
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

            var dialog = document.querySelector('#{$modalId} .modal-dialog');
            if (dialog) {
                dialog.style.maxWidth = '70vw';
                dialog.style.width = '70vw';
            }
        });
    JS;
    echo Html::scriptBlock($js);
}
