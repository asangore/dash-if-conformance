#/bin/sh

# docker build --build-arg SHORT_SHA=$(git rev-parse HEAD) -t dash-if-conformance:latest -t dash-if-conformance:$(git rev-parse --short HEAD) .
version=$1
docker build --network=host --platform linux/amd64 -t harbor.infra.smf1.mobitv/application/tools/dash-if-conformance-tool:$version .