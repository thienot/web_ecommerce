# docker/redis/Dockerfile
FROM redis:alpine

# Copy custom Redis configuration if exists
# COPY ./redis/redis.conf /usr/local/etc/redis/redis.conf

# Expose Redis port
EXPOSE 6379

# Start Redis server
CMD ["redis-server"]