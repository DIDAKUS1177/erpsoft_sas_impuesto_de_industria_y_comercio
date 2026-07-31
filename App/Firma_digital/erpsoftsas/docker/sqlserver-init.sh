#!/bin/bash
# Espera a que SQL Server arranque y luego importa la base de datos

echo "[INIT] Esperando a que SQL Server este listo..."

for i in $(seq 1 40); do
    /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Server2019" -Q "SELECT 1" -No &>/dev/null
    if [ $? -eq 0 ]; then
        echo "[INIT] SQL Server listo."
        break
    fi
    echo "[INIT] Intento $i/40 - esperando 3s..."
    sleep 3
done

# Crear la base de datos si no existe
echo "[INIT] Creando base de datos erpsoftweb (si no existe)..."
/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Server2019" -No \
    -Q "IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'erpsoftweb') CREATE DATABASE [erpsoftweb]"

# Verificar si ya tiene tablas (evitar reimportar en reinicios)
TABLE_COUNT=$(/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Server2019" -d erpsoftweb -No \
    -Q "SET NOCOUNT ON; SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE'" \
    2>/dev/null | tr -d '[:space:]')

if [ "$TABLE_COUNT" = "0" ] || [ -z "$TABLE_COUNT" ]; then
    echo "[INIT] Importando bd-industria_comercio.sql..."
    /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Server2019" \
        -i /docker/bd-industria_comercio.sql -No
    echo "[INIT] Importacion completada."
else
    echo "[INIT] Base de datos ya tiene datos ($TABLE_COUNT tablas). Saltando importacion."
fi

# Ejecutar script de migracion de conf_usuario (siempre es seguro correrlo porque usa IF NOT EXISTS)
echo "[INIT] Importando sql_migracion_conf_usuario.sql..."
/opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Server2019" -d erpsoftweb \
    -i /var/www/html/erpsoftsas/microservicios/firmas/sql_migracion_conf_usuario.sql -No

echo "[INIT] SQL Server inicializado correctamente."
