# Pinear a Debian 12 (Bookworm) — Debian 13 (Trixie) no tiene soporte de Microsoft
FROM php:8.1-apache-bookworm

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    unixodbc-dev \
    curl \
    gnupg2 \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# Microsoft ODBC Driver 18 para Debian 12 (Bookworm)
# Key en /usr/share/keyrings/ que es donde apunta el prod.list de Microsoft
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list \
        -o /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 \
    && rm -rf /var/lib/apt/lists/*

# Extensiones PHP: sqlsrv (SQL Server) + mysqli (MySQL) + GD (imágenes para TCPDF)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd mysqli

RUN pecl install sqlsrv-5.12.0 \
    && pecl install pdo_sqlsrv-5.12.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Permitir .htaccess en /var/www/html
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
