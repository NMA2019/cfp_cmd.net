#!/bin/bash

# Script de push automatique vers GitHub

# Se placer dans le rpertoire du script (optionnel)
cd "$(dirname "$0")"

# Ajouter tous les fichiers modifis
git add .

# Commit avec un message horodat
git commit -m "Mise  jour automatique - $(date '+%Y-%m-%d %H:%M:%S')"

# Pousser les changements vers la branche main
git push origin main

