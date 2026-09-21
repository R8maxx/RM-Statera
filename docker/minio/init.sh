#!/bin/sh
set -e

# Crea los tres buckets que la aplicación espera. Antes era un paso manual en la
# consola de MinIO, y olvidarlo se manifestaba mucho después: la subida de una
# evidencia fallando con un error de S3 que no dice «el bucket no existe».
#
# El versionado se activa porque en producción los dos buckets lo llevan (§6 del
# stack). El Object Lock sobre el prefijo `emitidas/` es de producción y no se
# reproduce aquí: bloquearía la regeneración del borrador, que es la operación
# normal del módulo de documentos.

until mc alias set statera "http://minio:9000" "${MINIO_ROOT_USER}" "${MINIO_ROOT_PASSWORD}" >/dev/null 2>&1; do
    echo '[statera] esperando a MinIO…'
    sleep 1
done

for bucket in "${AWS_BUCKET}" "${AWS_BUCKET_DOCUMENTOS}" "${AWS_BUCKET_ADJUNTOS}"; do
    mc mb --ignore-existing "statera/${bucket}"
    mc version enable "statera/${bucket}"
    echo "[statera] bucket ${bucket} listo"
done
