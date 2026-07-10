FROM eclipse-temurin:21-jre-alpine

# hadolint ignore=DL3018
RUN apk add --no-cache curl bzip2 tar

WORKDIR /photon

# Pinned Photon release (github.com/komoot/photon). The search index itself is
# NOT baked in here — it's downloaded once at container startup into a named
# volume (see entrypoint.sh), so rebuilding this image never re-downloads the
# ~5GB France+Monaco dataset.
RUN curl -fL -o photon.jar https://github.com/komoot/photon/releases/download/1.2.1/photon-1.2.1.jar

COPY docker/photon/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 2322

ENTRYPOINT ["/entrypoint.sh"]
CMD ["java", "-jar", "photon.jar", "serve", "-listen-ip", "0.0.0.0"]
