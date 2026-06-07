# docker/mysql/Dockerfile
FROM mysql:8.0

# Copy custom MySQL configuration
COPY docker/mysql/my.cnf /etc/mysql/my.cnf

# Copy initialization scripts
COPY docker/mysql/init /docker-entrypoint-initdb.d

# Set default authentication plugin
CMD ["mysqld", "--default-authentication-plugin=mysql_native_password"]