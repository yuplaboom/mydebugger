#!/bin/bash
# Un crontab tourne pour excuter ce fichier
total_mails=$(mail -H | wc -l)
nb=10
# Si plus de 10 mails, supprimer les plus anciens
if [ "$total_mails" -gt "$nb" ]; then
  mails_to_delete=$((total_mails - nb))
  for i in $(seq 1 $mails_to_delete); do
    echo "d" | mail -N
  done
  echo "w" | mail -N  # Enregistre les modifications
fi
