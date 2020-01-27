FROM eu.gcr.io/sigma-development-264720/php-fpm:latest

# Copy the entrypoint file into the image
COPY .devcontainer/scheduler/entrypoint.sh /opt/bin/entrypoint.sh

# Make the entrypoint executable
RUN chmod +x /opt/bin/entrypoint.sh

# Override the default fpm command
CMD ["/opt/bin/entrypoint.sh"]
