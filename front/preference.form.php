<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight(PluginCreditalertProfile::RIGHTNAME_TRANSFER, PluginCreditalertProfile::RIGHT_TRANSFER);

$userId = Session::getLoginUserID();
$postedUserId = (int) ($_POST['users_id'] ?? 0);
if ($postedUserId <= 0 || $postedUserId !== $userId) {
    Html::displayRightError();
}

if (isset($_POST['update_prefs'])) {
    if (PluginCreditalertPreference::saveUserPreferences($userId, $_POST)) {
        Session::addMessageAfterRedirect(__('Preferences updated.', 'creditalert'), true, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Preferences update failed.', 'creditalert'), true, ERROR);
    }
}

/** @var array $CFG_GLPI */
global $CFG_GLPI;
$redirect = $CFG_GLPI['root_doc'] . '/front/preference.php';
Html::redirect($redirect);
