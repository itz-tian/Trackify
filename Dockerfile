# Dockerfile for Railway deployment
FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy all application code
COPY . /app

# Ensure uploads folder is writable
RUN chmod -R 777 /app/uploads

EXPOSE 8080

# Entrypoint: print DB env vars on startup (visible in Railway logs), then write a PHP env cache, then start server
CMD ["sh", "-c", "\
  echo '=== RAILWAY ENV VARS ===' && \
  echo \"MYSQLHOST=$MYSQLHOST\" && \
  echo \"MYSQLPORT=$MYSQLPORT\" && \
  echo \"MYSQLUSER=$MYSQLUSER\" && \
  echo \"MYSQLDATABASE=$MYSQLDATABASE\" && \
  echo \"DATABASE_URL=$DATABASE_URL\" && \
  echo \"MYSQLPASSWORD=[hidden]\" && \
  echo '========================' && \
  php -S 0.0.0.0:${PORT:-8080} -t /app \
"]
