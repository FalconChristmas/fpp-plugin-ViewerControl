#!/bin/bash

pushd $(dirname $(which $0))

mkdir -p tmp

chown fpp.fpp tmp

popd

