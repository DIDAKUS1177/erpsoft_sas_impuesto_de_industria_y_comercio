#!/bin/bash
# Importa el esquema de MySQL en la base erpsoftsas

echo "[INIT] Importando esquema MySQL en base erpsoftsas..."
mysql -u root erpsoftsas < /docker/erpsoftsas.sql
echo "[INIT] MySQL inicializado correctamente."
