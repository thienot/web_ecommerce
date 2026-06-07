# docker/phpmyadmin/Dockerfile
FROM phpmyadmin/phpmyadmin

# Install curl for healthcheck
RUN apt-get update && apt-get install -y curl wget && rm -rf /var/lib/apt/lists/*

# Default phpMyAdmin configuration
ENV PMA_ARBITRARY=1
ENV PMA_HOST=db

# Expose port
EXPOSE 80