# docker/nginx/Dockerfile
FROM nginx:alpine

# Install curl for healthcheck
RUN apk add --no-cache curl

# Copy nginx configuration
COPY docker/nginx/conf.d /etc/nginx/conf.d
COPY docker/nginx/ssl /etc/nginx/ssl

# Create health-check endpoint
RUN echo '<?php echo "OK"; ?>' > /usr/share/nginx/html/health-check

# Set working directory
WORKDIR /var/www/html

# Expose ports
EXPOSE 80 443

# Start nginx
CMD ["nginx", "-g", "daemon off;"]