# Updater sidecar: git + docker CLI + node, talks to the host docker daemon through the mounted socket.
FROM docker:27-cli
RUN apk add --no-cache nodejs git bash docker-cli-compose
WORKDIR /updater
COPY server.mjs ./
ENV PORT=4100 HOST=0.0.0.0
EXPOSE 4100
CMD ["node", "server.mjs"]
