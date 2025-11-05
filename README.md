# gardemk

Application MVP pour gestion des gardes des masseurs-kinés (PHP 8.4, jQuery, Bootstrap).

Structure principale:
- public/: webroot (index.php, planning.php, profil.php, gardes_passees.php, saisie.php, assets/)
- src/inc/: configuration et helpers (config.php, db.php, auth.php, helpers.php)
- sql/schema.sql: schéma de base de données

Installation rapide:
1. Copier `.env.example` en `.env` et renseigner les variables (DB, BREVO_API_KEY si besoin).
2. Importer `sql/schema.sql` dans MySQL/MariaDB.
3. Placer le contenu du dossier `public/` comme webroot.
4. Lancer `composer install` si nécessaire (aucune dépendance pour l'instant).

Notes:
- Authentification simple par adresse email (connexion sans mot de passe, session).
- Pas de stockage du RIB, juste un flag `rib_provided`.
- Brevo: clé API attendue dans la config (non incluse).