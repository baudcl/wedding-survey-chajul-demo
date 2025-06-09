# Guide : Gestion séparée des enfants dans le plan de table

## 🎯 Objectif
Cette fonctionnalité permet de placer les adultes et les enfants d'une même famille à des tables différentes, idéal pour créer une table dédiée aux enfants.

## 🔧 Installation

### 1. Exécuter le script de migration
Avant d'utiliser la nouvelle fonctionnalité, exécutez le script de migration :

```bash
php admin/migration_script.php
```

Ce script va :
- Vérifier si votre base de données est déjà à jour
- Migrer automatiquement les affectations existantes
- Préserver toutes vos données actuelles

## 📖 Comment utiliser la fonctionnalité

### Vue d'ensemble dans la liste des invités

Dans la barre latérale "Invités à placer", vous verrez maintenant :

1. **Famille complète** (fond beige si partiellement placée)
   - Affiche le nom de famille avec le nombre total de personnes
   - Indique si certains membres sont déjà placés

2. **Adultes seulement** (fond bleu clair)
   - Permet de placer uniquement les adultes de la famille
   - Affiche le nombre d'adultes restant à placer

3. **Enfants seulement** (fond bleu clair)
   - Permet de placer uniquement les enfants
   - Affiche le nombre d'enfants restant à placer

### Exemple pratique

**Famille Dupont** : 2 adultes + 3 enfants

#### Option 1 : Placer toute la famille ensemble
1. Glissez "**Famille Dupont**" (2 👤 + 3 👶) sur une table
2. Les 5 personnes sont placées ensemble

#### Option 2 : Séparer adultes et enfants
1. Glissez "**2 adultes seulement**" sur la Table 1 (table des adultes)
2. Glissez "**3 enfants seulement**" sur la Table 8 (table des enfants)

### Indicateurs visuels

- **Fond beige** : Famille partiellement placée
- **Fond bleu** : Option de placement partiel (adultes ou enfants seuls)
- **Texte orange** : Indique où sont placés les membres déjà affectés

### Dans le détail d'une table

Quand vous cliquez sur une table, vous voyez :
- Qui est placé à cette table
- Le détail adultes/enfants pour chaque famille
- L'indication "(partiel)" si tous les membres de la famille ne sont pas à cette table

## 💡 Cas d'usage

### Table d'enfants avec animateurs
1. Créez une table "Table des enfants" avec une capacité adaptée
2. Placez tous les enfants des différentes familles sur cette table
3. Les parents restent sur leurs tables respectives

### Séparation par âge
1. Table des tout-petits (0-5 ans)
2. Table des enfants (6-12 ans)
3. Table des ados (13-17 ans)

### Organisation mixte
- Certaines familles restent ensemble
- D'autres séparent adultes et enfants
- Flexibilité totale selon vos besoins

## ⚠️ Points d'attention

1. **Capacité des tables** : Vérifiez toujours que la table a assez de places
2. **Retour en arrière** : Pour regrouper une famille, glissez à nouveau la "Famille complète"
3. **Export** : Le plan exporté indique clairement la répartition adultes/enfants

## 🔄 Retour à l'ancienne version

Pour la version sans séparation :
- Utiliser toujours l'option "Famille complète"
- Ignorer les options "adultes seulement" et "enfants seulement"

## 📊 Statistiques

Le tableau de bord affiche maintenant :
- Nombre total de places occupées
- Détail : X adultes, Y enfants
- Permet de vérifier l'équilibre de votre plan