# Scripts de Release

Ce dossier contient des scripts pour gérer les releases du package Laravel Draftable.

## Scripts disponibles

### `release.sh`
Créer une nouvelle release et la publier sur GitHub et Packagist.

**Usage :**
```bash
./release.sh <version> [notes_de_release]
```

**Exemples :**
```bash
# Release simple
./release.sh 1.2.0

# Release avec notes
./release.sh 1.2.0 "Ajout du diff entre versions et amélioration des performances"

# Release avec notes multilignes
./release.sh 1.2.0 "
- Ajout de la commande draftable:diff
- Correction des erreurs PHPStan
- Amélioration de la documentation
"
```

### `check-releases.sh`
Vérifier l'état des releases et des tags.

**Usage :**
```bash
./check-releases.sh
```

## Prérequis

### GitHub CLI
Pour utiliser les scripts, vous devez avoir GitHub CLI installé et configuré :

```bash
# Ubuntu/Debian
sudo apt install gh

# macOS
brew install gh

# Connexion
gh auth login
```

## Workflow de release

1. **Développement** : Faites vos modifications et committez normalement
2. **Vérification** : `./check-releases.sh` pour voir l'état actuel
3. **Release** : `./release.sh X.Y.Z "Description"` quand prêt à publier
4. **Suivi** : Le workflow GitHub Actions s'occupe du reste

## Processus automatique

Quand vous lancez `./release.sh` :

1. ✅ **Vérifications** : Format version, état du repo, permissions
2. 📤 **Push** : Pousse les derniers changements
3. 🚀 **Déclenchement** : Lance le workflow GitHub Actions
4. 🧪 **Tests** : Exécute la suite de tests complète (Pest + PHPStan)
5. 🏷️ **Tag** : Crée et pousse le tag Git (seulement si tests OK)
6. 📦 **Release** : Crée la release GitHub (seulement si tag OK)
7. 🌐 **Packagist** : Mise à jour automatique via webhook

## Versioning

Utilisez le [Semantic Versioning](https://semver.org/) :
- **Major** (X.0.0) : Changements incompatibles
- **Minor** (X.Y.0) : Nouvelles fonctionnalités compatibles
- **Patch** (X.Y.Z) : Corrections de bugs

## Remarques

- Seul `grazulex` peut déclencher des releases (configuré dans le workflow)
- **Les tests doivent passer avant la création de la release** (obligatoire)
- La release est annulée si les tests échouent
- Packagist se met à jour automatiquement grâce au webhook GitHub
