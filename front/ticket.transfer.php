<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight(PluginCreditalertProfile::RIGHTNAME_TRANSFER, PluginCreditalertProfile::RIGHT_TRANSFER);

/** @var array $CFG_GLPI */
global $CFG_GLPI;

$ticketId = (int) ($_GET['tickets_id'] ?? ($_POST['tickets_id'] ?? 0));
if ($ticketId <= 0) {
    Html::displayErrorAndDie(__('Missing ticket.', 'creditalert'));
}

$ticket = new Ticket();
if (!$ticket->getFromDB($ticketId)) {
    Html::displayErrorAndDie(__('Ticket not found.', 'creditalert'));
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
    Html::displayRightError();
}

$baseUrl = $CFG_GLPI['root_doc'] . '/plugins/creditalert/front/ticket.transfer.php?tickets_id=' . $ticketId;
if (!empty($_REQUEST['_in_modal'])) {
    $baseUrl .= '&_in_modal=1';
}

$preferences = PluginCreditalertPreference::getUserPreferences(Session::getLoginUserID());
$assignOnTransfer = (int) $preferences['assign_on_transfer'] === 1;
$replaceOnTransfer = (int) $preferences['replace_on_transfer'] === 1;
$replaceOnTransfer = $assignOnTransfer ? $replaceOnTransfer : false;
$transferComment = '';

$errors = [];
$selectedUsers = [];

if (isset($_POST['transfer'])) {
    $assignOnTransfer = !empty($_POST['assign_on_transfer']);
    $replaceOnTransfer = !empty($_POST['replace_on_transfer']);
    if (!$assignOnTransfer) {
        $replaceOnTransfer = false;
    }
    $transferComment = trim((string) ($_POST['transfer_comment'] ?? ''));

    $selectedUsers = $_POST['users_id'] ?? [];
    if (!is_array($selectedUsers)) {
        $selectedUsers = [$selectedUsers];
    }
    $selectedUsers = array_values(array_unique(array_filter(array_map('intval', $selectedUsers))));

    if (empty($selectedUsers)) {
        $errors[] = __('Veuillez selectionner au moins un utilisateur.', 'creditalert');
    }

    $recipients = [];
    $missingEmails = [];
    $invalidUsers = [];
    $validUsers = [];

    foreach ($selectedUsers as $userId) {
        $user = new User();
        if (!$user->getFromDB($userId) || (int) ($user->fields['is_active'] ?? 0) !== 1) {
            $invalidUsers[] = $userId;
            continue;
        }
        $email = $user->getDefaultEmail();
        if ($email === '') {
            $missingEmails[] = $user->getName();
            continue;
        }
        $recipients[] = $email;
        $validUsers[] = $userId;
    }

    if (!empty($invalidUsers)) {
        $errors[] = __('Certains utilisateurs selectionnes sont invalides.', 'creditalert');
    }
    if (!empty($missingEmails)) {
        $errors[] = sprintf(
            __('Adresse email manquante pour : %s', 'creditalert'),
            implode(', ', $missingEmails)
        );
    }
    if (empty($recipients)) {
        $errors[] = __('Aucune adresse email de destinataire trouvee.', 'creditalert');
    }

    if ($assignOnTransfer && !$ticket->canAssign()) {
        $errors[] = __('Vous n avez pas le droit d attribuer ce ticket.', 'creditalert');
    }

    if (empty($errors)) {
        if ($assignOnTransfer) {
            $ticketUser = new Ticket_User();

            if ($replaceOnTransfer) {
                /** @var DBmysql $DB */
                global $DB;
                $DB->delete('glpi_tickets_users', [
                    'tickets_id' => $ticketId,
                    'type'       => CommonITILActor::ASSIGN,
                ]);
                $DB->delete('glpi_groups_tickets', [
                    'tickets_id' => $ticketId,
                    'type'       => CommonITILActor::ASSIGN,
                ]);
                $DB->delete('glpi_suppliers_tickets', [
                    'tickets_id' => $ticketId,
                    'type'       => CommonITILActor::ASSIGN,
                ]);
            }

            $existing = [];
            foreach ($ticketUser->find([
                'tickets_id' => $ticketId,
                'type'       => CommonITILActor::ASSIGN,
            ]) as $row) {
                $existing[(int) $row['users_id']] = true;
            }

            foreach ($validUsers as $userId) {
                if (isset($existing[$userId])) {
                    continue;
                }
                $ticketUser->add([
                    'tickets_id' => $ticketId,
                    'users_id'   => $userId,
                    'type'       => CommonITILActor::ASSIGN,
                    '_from_object' => true,
                ]);
            }
        }

        $ticketPath = Ticket::getFormURLWithID($ticketId, false);
        if ($ticketPath !== '' && $ticketPath[0] !== '/') {
            $ticketPath = '/' . $ticketPath;
        }
        $ticketUrl = $ticketPath;
        if (!empty($CFG_GLPI['url_base'])) {
            $ticketUrl = rtrim($CFG_GLPI['url_base'], '/') . $ticketPath;
        }
        $senderName = getUserName(Session::getLoginUserID());
        $subject = sprintf('[GLPI] %s #%d', __('Ticket transfere', 'creditalert'), $ticketId);
        $textLines = [
            sprintf(__('Ticket #%d transfere par %s', 'creditalert'), $ticketId, $senderName),
            sprintf(__('Titre : %s', 'creditalert'), $ticket->getName()),
            sprintf(__('Lien : %s', 'creditalert'), $ticketUrl),
        ];
        $safeUrl = htmlescape($ticketUrl);
        $safeTitle = Html::entities_deep($ticket->getName());
        $safeSender = Html::entities_deep($senderName);
        $htmlLines = [
            sprintf(__('Ticket #%d transfere par %s', 'creditalert'), $ticketId, $safeSender),
            sprintf(__('Titre : %s', 'creditalert'), $safeTitle),
            sprintf(__('Lien : %s', 'creditalert'), "<a href=\"{$safeUrl}\">{$safeUrl}</a>"),
        ];
        if ($transferComment !== '') {
            $commentLabel = __('Commentaire', 'creditalert');
            $textLines[] = $commentLabel . ' : ' . $transferComment;
            $safeComment = nl2br(Html::entities_deep($transferComment));
            $htmlLines[] = $commentLabel . ' : ' . $safeComment;
        }

        $mailer = new GLPIMailer();
        $email = $mailer->getEmail();
        $email->subject($subject);
        $email->text(implode(PHP_EOL, $textLines));
        $email->html(implode('<br>', $htmlLines));
        foreach ($recipients as $recipient) {
            $email->addTo((string) $recipient);
        }
        if (!empty($CFG_GLPI['admin_email'])) {
            $email->from($CFG_GLPI['admin_email']);
        }

        if ($mailer->send()) {
            Session::addMessageAfterRedirect(__('Ticket transfere.', 'creditalert'), true, INFO);
            Html::redirect($baseUrl);
        } else {
            $errors[] = __('Email sending failed.', 'creditalert');
        }
    }
}

Html::header(
    __('Transferer ticket', 'creditalert'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'creditalert',
    'ticket'
);

Html::displayMessageAfterRedirect();

echo "<div class='card'><div class='card-body'>";
echo "<div class='mb-3'><strong>Ticket</strong> " . (int) $ticketId . " - " . Html::entities_deep($ticket->getName()) . "</div>";

if (!empty($errors)) {
    echo "<div class='alert alert-danger'>";
    foreach ($errors as $error) {
        echo "<div>" . Html::entities_deep($error) . "</div>";
    }
    echo "</div>";
}



echo "<form method='post' action='" . Html::cleanInputText($baseUrl) . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]);
echo Html::hidden('tickets_id', ['value' => $ticketId]);
if (!empty($_REQUEST['_in_modal'])) {
    echo Html::hidden('_in_modal', ['value' => 1]);
}

echo "<div class='mb-3'>";
echo "<label class='form-label mb-1'>" . __('Utilisateurs', 'creditalert') . "</label>";
User::dropdown([
    'name'         => 'users_id[]',
    'value'        => $selectedUsers,
    'multiple'     => true,
    'entity'       => -1,
    'entity_sons'  => false,
    'right'        => 'all',
    'width'        => '100%',
]);
echo "</div>";

echo "<div class='mb-3'>";
echo "<div class='form-check'>";
echo "<input class='form-check-input' type='checkbox' id='creditalert_assign_on_transfer' name='assign_on_transfer' value='1'"
    . ($assignOnTransfer ? ' checked' : '') . ">";
echo "<label class='form-check-label' for='creditalert_assign_on_transfer'>"
    . __('Associer les utilisateurs transferes dans "Attribue a"', 'creditalert') . "</label>";
echo "</div>";
echo "</div>";

echo "<div class='mb-3'>";
echo "<div class='form-check'>";
echo "<input class='form-check-input' type='checkbox' id='creditalert_replace_on_transfer' name='replace_on_transfer' value='1'"
    . ($replaceOnTransfer ? ' checked' : '') . (!$assignOnTransfer ? ' disabled' : '') . ">";
echo "<label class='form-check-label' for='creditalert_replace_on_transfer'>"
    . __('Remplacer les utilisateurs deja attribues', 'creditalert') . "</label>";
echo "</div>";
echo "</div>";

echo "<div class='alert alert-info mb-3' id='creditalert_transfer_info'>";
if (!$assignOnTransfer) {
    echo Html::entities_deep(__('Les utilisateurs transferes ne seront pas associes dans "Attribue a".', 'creditalert'));
} elseif ($replaceOnTransfer) {
    echo Html::entities_deep(__('Les utilisateurs transferes remplaceront les assignes existants.', 'creditalert'));
} else {
    echo Html::entities_deep(__('Les utilisateurs transferes seront ajoutes aux assignes existants.', 'creditalert'));
}
echo "</div>";

echo "<div class='mb-3'>";
echo "<label class='form-label mb-1' for='creditalert_transfer_comment'>" . __('Commentaire', 'creditalert') . "</label>";
echo "<textarea class='form-control' id='creditalert_transfer_comment' name='transfer_comment' rows='3'>"
    . Html::entities_deep($transferComment) . "</textarea>";
echo "</div>";

echo "<div class='text-end'>";
echo Html::submit(__('Transferer', 'creditalert'), ['name' => 'transfer', 'class' => 'btn btn-primary']);
echo "</div>";
echo "</form>";

echo "</div></div>";

$infoNoAssign = json_encode(__('Les utilisateurs transferes ne seront pas associes dans "Attribue a".', 'creditalert'));
$infoReplace = json_encode(__('Les utilisateurs transferes remplaceront les assignes existants.', 'creditalert'));
$infoAppend = json_encode(__('Les utilisateurs transferes seront ajoutes aux assignes existants.', 'creditalert'));
$js = <<<JS
(function() {
    var assign = document.getElementById('creditalert_assign_on_transfer');
    var replace = document.getElementById('creditalert_replace_on_transfer');
    var info = document.getElementById('creditalert_transfer_info');
    if (!assign || !replace || !info) {
        return;
    }
    var update = function() {
        if (!assign.checked) {
            replace.checked = false;
            replace.disabled = true;
            info.textContent = {$infoNoAssign};
            return;
        }
        replace.disabled = false;
        if (replace.checked) {
            info.textContent = {$infoReplace};
        } else {
            info.textContent = {$infoAppend};
        }
    };
    assign.addEventListener('change', update);
    replace.addEventListener('change', update);
    update();
})();
JS;
echo Html::scriptBlock($js);

Html::footer();
