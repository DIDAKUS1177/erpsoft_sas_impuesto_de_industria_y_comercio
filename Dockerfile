FROM php:8.1-apache

# Instalar dependencias necesarias para los drivers de SQL Server
RUN apt-get update && apt-get install -y \
    gnupg2 \
    curl \
    apt-transport-https \
    unixodbc-dev \
    libmcrypt-dev \
    libonig-dev

# Añadir llaves y repositorios de Microsoft para msodbcsql18
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl https://packages.microsoft.com/config/debian/12/prod.list | tee /etc/apt/sources.list.d/mssql-release.list

# Instalar msodbcsql18
RUN apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 \
    && echo 'export PATH="$PATH:/opt/mssql-tools18/bin"' >> ~/.bashrc

# Instalar extensiones de PHP para SQL Server (versión 5.12.0 es compatible con PHP 8.1)
RUN pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Habilitar mod_rewrite en Apache
RUN a2enmod rewrite

# Ajustar permisos
RUN chown -R www-data:www-data /var/www/html
