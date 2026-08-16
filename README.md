# CreditAlert

Plugin GLPI de surveillance des crédits du plugin `credit`: seuils d'alerte, vues de consommation/synthèse, exports CSV et notifications email (avec gestion anti-duplication) pour anticiper les dépassements ou crédits faibles.

Le plugin sert à transformer des données de crédits en alertes exploitables par l'équipe (commerciale, support, gestion, supervision).

## Ce que fait le plugin (lecture rapide)

- Affiche des vues de consommation et de synthèse.
- Permet de réaffecter les consommations d'un ticket vers un autre crédit
  (fenêtre modale depuis la timeline, responsive depuis la v1.2.0 : large sur
  ordinateur, panneau pleine largeur ancré en bas de l'écran sur téléphone).
- Déclenche des alertes selon des seuils (globaux et par entité).
- Permet des overrides par entité.
- Exporte les données en CSV.
- Envoie des notifications par email via cron GLPI.
- S'adapte aux schémas du plugin `credit` via un mapping configurable des tables/champs.

## Fonctionnement (parcours type)

1. Installer/activer `creditalert` avec le plugin `credit` déjà opérationnel.
2. Ouvrir la configuration et définir le seuil d'alerte global.
3. Configurer les destinataires de notification et les couleurs d'affichage.
4. Vérifier/adapter le mapping vers les tables/champs du plugin `credit` (si votre modèle diffère).
5. Définir des seuils spécifiques par entité si nécessaire.
6. Consulter les vues `consommations` / `synthèse` et exporter CSV.
7. Laisser le cron envoyer les alertes automatiquement.

## Configuration plugin (ce que chaque zone active)

### Alertes générales

- `alert_threshold`: seuil d'alerte global (warning).
- `color_warning`: couleur utilisée pour l'état d'alerte.
- `color_over`: couleur utilisée pour le dépassement.
- `notification_emails`: liste des destinataires des alertes.

### Mapping vers le plugin `credit`

Le plugin peut être configuré pour pointer sur des tables/champs spécifiques du plugin `credit`:
- table des crédits
- table de consommation
- champs solde/consommé/entité/client/clé étrangère/date de fin/actif/ticket

Pourquoi c'est important:
- certaines installations customisent le schéma ou la logique du plugin `credit`
- ce mapping évite de modifier le code de `creditalert` pour s'adapter

### Exports

Options d'export (selon version):
- `export_filename_base`
- ajout de la date et/ou de l'entité dans le nom de fichier

Export des consommations (v1.1.1) :
- fichier **Excel (.xlsx)** via PhpSpreadsheet : `TACHE n :` en gras dans la colonne tâches (🔒 pour une tâche privée), ligne vide entre les tâches, en-têtes figés
- tâches privées **exclues par défaut**, choix Oui/Non proposé dans la massive action « Exporter CSV » et dans le dialogue « Exporter toutes les pages »

### Overrides par entité

- `entities_id`
- `alert_threshold_entity`
- `notification_emails_entity`

À quoi ça sert:
- définir des seuils différents selon l'entité (ex: VIP, contrat spécifique, filiale)
- envoyer les alertes à des destinataires différents selon l'organisation

## Prérequis

- Plugin `credit` obligatoire
- GLPI 11.x (selon version installée)
- PHP compatible GLPI
- Cron GLPI fonctionnel pour les notifications automatiques

## Droits / profils

- Le plugin gère ses droits via le profil `CreditAlert` (lecture / configuration selon profil).
- Les vues et exports suivent ces droits plugin + les droits GLPI applicables.

## Tâches cron

Le plugin s'appuie sur un cron GLPI pour l'envoi des notifications.

Comportement attendu:
- détection des lignes en alerte / dépassement
- génération du message vers les destinataires configurés
- anti-duplication via hash pour éviter les doubles notifications inutiles

## Architecture (résumé court)

- Une configuration centrale contient seuils, couleurs, destinataires et mapping `credit`.
- Des vues SQL (maintenues par le plugin) servent de base aux écrans de synthèse/consommation.
- Le cron lit ces données, calcule les alertes et envoie les notifications de façon dédupliquée.

## Vérifications rapides après mise à jour

- Ouvrir les écrans de consommation et synthèse.
- Vérifier le mapping `credit` si votre plugin `credit` est customisé.
- Tester un export CSV.
- Simuler un seuil atteint pour vérifier l'affichage warning/over.
- Vérifier les emails d'alerte via le cron GLPI.
